<?php

namespace app\controllers\cp;

use Yii;
use app\models\{Image, ImageSearch, Manager, I18n};
use yii\web\{BadRequestHttpException, NotFoundHttpException, Response};
use yii\filters\{AccessControl, VerbFilter};
use yii\web\UploadedFile;
use yii\validators\FileValidator;
use app\components\S3;
use app\components\utils\ImageUtils;
use MongoDB\BSON\ObjectId;

/**
 * ImageController implements the CRUD actions for Image model.
 */
class ImageController extends CpController
{
    
    protected array $access_roles = [
        Manager::ROLE_ADMIN,
        Manager::ROLE_MANAGER
    ];
    
    
    /**
     * {@inheritdoc}
     */
    public function behaviors()
    {
        $behaviors = parent::behaviors();
        $behaviors['verbs']['actions'] = [
            'delete' => ['POST'],
            'search' => ['POST'],
            'image-upload' => ['POST']
        ];
        
        return $behaviors;
    }

    /**
     * Lists all Image models.
     * @return mixed
     */
    public function actionIndex()
    {
        $searchModel = new ImageSearch();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Displays a single Image model.
     * @param integer $_id
     * @return mixed
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionView($id)
    {
        return $this->render('view', [
            'model' => $this->findModel($id),
        ]);
    }

    /**
     * Creates a new Image model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return mixed
     */
    public function actionCreate()
    {
        $model = new Image();
        $translation_languages = I18n::getTranslationLanguages();

        if ($model->load(Yii::$app->request->post())) {
            //validate
            $validate_data = $this->validateLanguageFiles($translation_languages);
            $text_errors = $validate_data['text_errors'];
            $lang_errors_array = $validate_data['lang_errors_array'];

            $uploaded_files_data = ImageUtils::uploadLanguageFilesToS3($translation_languages, $lang_errors_array, $model);
            $urls = $uploaded_files_data['urls'];
            $hashes = $uploaded_files_data['hashes'];

            $model->url = $urls;
            $model->hashes = $hashes;

            if (in_array('en', $lang_errors_array)) {
                Yii::$app->session->setFlash('text_errors', $text_errors);
            }
            if (!in_array('en', $lang_errors_array) && $model->save()) {
                if ($text_errors) {
                    Yii::$app->session->setFlash('text_errors', $text_errors);
                    return $this->redirect(['update', 'id' => (string)$model->_id]);
                } else {
                    return $this->redirect(['view', 'id' => (string)$model->_id]);
                }
            }
        }
        return $this->render('create', [
            'model' => $model,
            'translation_languages' => $translation_languages,
        ]);
    }

    /**
     * Updates an existing Image model.
     * If update is successful, the browser will be redirected to the 'view' page.
     * @param string $id
     * @return mixed
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionUpdate($id)
    {
        $model = $this->findModel($id);
        $translation_languages = I18n::getTranslationLanguages();

        if ($model->load(Yii::$app->request->post())) {
            //validate
            $validate_data = $this->validateLanguageFiles($translation_languages);
            $text_errors = $validate_data['text_errors'];
            $lang_errors_array = $validate_data['lang_errors_array'];

            $uploaded_files_data = ImageUtils::uploadLanguageFilesToS3($translation_languages, $lang_errors_array, $model);
            $urls = $uploaded_files_data['urls'];
            $hashes = $uploaded_files_data['hashes'];

            //check and delete old images
            foreach ($model->url as $lang => $url) {
                if (isset($urls[$lang]) && $url != $urls[$lang] && !in_array($lang, $lang_errors_array)) {
                    ImageUtils::deleteFileFromS3($url, $model->category);
                }
            }

            $model->url = $urls;
            $model->hashes = $hashes;

            if (in_array('en', $lang_errors_array)) {
                Yii::$app->session->setFlash('text_errors', $text_errors);
            }
            if (!in_array('en', $lang_errors_array) && $model->save()) {
                if ($text_errors) {
                    Yii::$app->session->setFlash('text_errors', $text_errors);
                    return $this->redirect(['update', 'id' => (string)$model->_id]);
                } else {
                    return $this->redirect(['view', 'id' => (string)$model->_id]);
                }
            }
        }
        return $this->render('update', [
            'model' => $model,
            'translation_languages' => $translation_languages,
        ]);
    }

    /**
     * Validate uploaded language files
     * If type and size not a valid return text errors and languages when it happens
     * @param array $translation_languages
     * @return array
     */
    private function validateLanguageFiles(array $translation_languages): array
    {
        $text_errors = '';
        $lang_errors_array = [];
        foreach ($translation_languages as $lang => $value) {
            $file = UploadedFile::getInstanceByName("Image[images][{$lang}]");
            if (!empty($file)) {
                if (!$this->isValidImageUpload($file)) {
                    $text_errors .= 'Image "' . $value . '" is not valid <br />';
                    $lang_errors_array[] = $lang;
                }
            }
        }
        return [
            'text_errors' => $text_errors,
            'lang_errors_array' => $lang_errors_array
        ];
    }

    /**
     * Deletes an existing Image model.
     * If deletion is successful, the browser will be redirected to the 'index' page.
     * @param integer $_id
     * @return mixed
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionDelete($id)
    {
        $this->findModel($id)->delete();
        return $this->redirect(['index']);
    }

    /**
     * Finds the Image model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param integer $_id
     * @return Image the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = Image::findOne($id)) !== null) {
            return $model;
        }

        throw new NotFoundHttpException('The requested page does not exist.');
    }

    /**
     * Search action
     * @return \yii\web\Response
     */
    public function actionSearch()
    {
        $category = Yii::$app->request->post('category');
        $text = Yii::$app->request->post('text');
        $images = Image::searchImagesByCategory($category, ['name', 'url.en', 'category'], $text);
        return $this->asJson($images);
    }

    /**
     * Image upload
     * @param string $category
     * @return array|string[]
     * @throws BadRequestHttpException
     */
    public function actionImageUpload(string $category)
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $file = UploadedFile::getInstanceByName('file');
        if ($file) {
            if (!$this->isValidImageUpload($file)) {
                return ['error' => 'File not a valid.'];
            }
            $file_hash = hash_file('md5', $file->tempName);
            $image = Image::getByFileHash($file_hash);
            if (!$image) {
                $image = new Image();
                $image->name = uniqid();
                $image->category = $category;
                $image->hashes = ['hash' => $file_hash, 'language' => I18n::PRIMARY_LANGUAGE];
                $s3 = new S3();
                $file = $s3->put($file, $image->name, $s3->filepath_by_category[$image->category]);
                if (empty($file['@metadata']['effectiveUri'])) {
                    return ['error' => 'File could not be uploaded.'];
                }
                $urls[I18n::PRIMARY_LANGUAGE] = $file['@metadata']['effectiveUri'];
                $image->url = $urls;
                if (!$image->save()) {
                    return ['error' => 'File could not be uploaded.'];
                }
            }
            return ['id' => $image->name, 'filelink' => $image->url[I18n::PRIMARY_LANGUAGE] ?? ''];
        } else {
            return ['error' => 'File could not be uploaded.'];
        }
    }

    /**
     * Validate uploaded file for type and filesize
     * @param $file
     * @return bool
     */
    private function isValidImageUpload($file): bool
    {
        $error = true;
        $validator = new FileValidator();
        $validator->mimeTypes = Image::MIME_TYPES;
        $validator->minSize = Image::MIN_SIZE;
        $validator->maxSize = Image::MAX_SIZE;
        if (!$validator->validate($file)) {
            $error = false;
        }
        return $error;
    }

    /**
     * Check unique
     * @param string $name
     * @param string|null $id
     * @return int
     */
    public function actionCheckUniqueName(string $name, ?string $id = null): int
    {
        if ($id) {
            $model = Image::find()->where([
                'and',
                ['name' => $name],
                ['<>', '_id', new ObjectID($id)]
            ])->one();
        } else {
            $model = Image::find()->where(['name' => $name])->one();
        }

        return isset($model->name) ? 1 : 0;
    }
}
