<?php
namespace app\components;

use Yii;
use yii\helpers\Html;

/**
 * Class View
 * @package app\components
 */
class PublicView extends \yii\web\View
{

    /**
     * Marks the ending of an HTML body section.
     */
    public function endBody()
    {
        // Delete jquery and boostrap bundles for all places except admin panel `cp/`
        if ((!isset(Yii::$app->controller->id) || substr( Yii::$app->controller->id, 0, 3 ) !== 'cp/')) {
            // for 404 cp, because here is site controller
            if ((Yii::$app->controller->id === 'site' && strpos(Yii::$app->request->url, '/cp/') === false)) {
                $asset_bundles = $this->assetBundles;
                unset($asset_bundles['yii\web\JqueryAsset']);
                unset($asset_bundles['yii\bootstrap4\BootstrapPluginAsset']);
                unset($asset_bundles['yii\bootstrap4\BootstrapAsset']);
                $this->assetBundles = $asset_bundles;
            }
        }
        parent::endBody();
    }


    /**
     * Renders the content to be inserted at the end of the body section.
     * The content is rendered using the registered JS code blocks and files.
     * @param bool $ajaxMode whether the view is rendering in AJAX mode.
     * If true, the JS scripts registered at [[POS_READY]] and [[POS_LOAD]] positions
     * will be rendered at the end of the view like normal scripts.
     * @return string the rendered content
     */
    protected function renderBodyEndHtml($ajaxMode)
    {
        //echo '<pre>'; var_dump(Yii::$app->controller->id); echo '</pre>'; exit;
        //$asset_manager = $this->getAssetManager();
        //unset($asset_manager->bundles['yii\web\JqueryAsset']);
        //echo '<pre>'; var_dump($asset_manager); echo '</pre>'; exit;
        $lines = [];

        if (!empty($this->jsFiles[self::POS_END])) {
            $lines[] = implode("\n", $this->jsFiles[self::POS_END]);
        }

        if ($ajaxMode) {
            $scripts = [];
            if (!empty($this->js[self::POS_END])) {
                $scripts[] = implode("\n", $this->js[self::POS_END]);
            }
            if (!empty($this->js[self::POS_READY])) {
                $scripts[] = implode("\n", $this->js[self::POS_READY]);
            }
            if (!empty($this->js[self::POS_LOAD])) {
                $scripts[] = implode("\n", $this->js[self::POS_LOAD]);
            }
            if (!empty($scripts)) {
                $lines[] = Html::script(implode("\n", $scripts));
            }
        } else {
            if (!empty($this->js[self::POS_END])) {
                $lines[] = Html::script(implode("\n", $this->js[self::POS_END]));
            }
            if (!empty($this->js[self::POS_READY])) {
                $js = "document.addEventListener('DOMContentLoaded', function(event) {\n" . implode("\n", $this->js[self::POS_READY]) . "\n});";
                $lines[] = Html::script($js);
            }
            if (!empty($this->js[self::POS_LOAD])) {
                $js = "document.addEventListener('DOMContentLoaded', function(event) {\n" . implode("\n", $this->js[self::POS_LOAD]) . "\n});";
                $lines[] = Html::script($js);
            }
        }

        return empty($lines) ? '' : implode("\n", $lines);
    }
}
