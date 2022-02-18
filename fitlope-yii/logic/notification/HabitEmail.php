<?php

namespace app\logic\notification;

use Yii;
use app\models\{User, BlogPost};
use app\components\helpers\Url;

/**
 * Class HabitEmail
 * @package app\logic\notification
 */
class HabitEmail
{
    // how much minutes for emailing
    const TYPE_BEFORE_MINS = [
        '1' => 1440,
        '2' => 2880,
        '3' => 4320,
        '4' => 5760,
        '5' => 7200,
        '6' => 8640,
        '7' => 10080
    ];

    // place by habit email type
    const PLACE_BY_TYPE = [
        '1' => Email::PLACE_HABIT_1,
        '2' => Email::PLACE_HABIT_2,
        '3' => Email::PLACE_HABIT_3,
        '4' => Email::PLACE_HABIT_4,
        '5' => Email::PLACE_HABIT_5,
        '6' => Email::PLACE_HABIT_6,
        '7' => Email::PLACE_HABIT_7
    ];

    // how often cron starts
    const CRON_TIMING_MINUTES = 5;

    protected User $user;
    protected string $type;
    protected ?array $blogs = null;
    protected ?string $host = null;

    /**
     * HabitEmail constructor.
     * @param User $user - name, email, language is required for select
     * @param string $type
     */
    public function __construct(User $user, string $type)
    {
        $this->user = $user;
        $this->type = $type;
    }

    /**
     * Send an email
     * @return bool
     */
    public function send(): bool
    {
        $added = false;
        if (!empty(static::PLACE_BY_TYPE[$this->type])) {
            $args = $this->getEmailPlaceholders($this->type);
            $email = new Email($this->user->email, static::PLACE_BY_TYPE[$this->type], 'email.subject.habit_' . $this->type);
            $email->translate($this->user->language, $args);
            $added = $email->queue();
        } else {
            Yii::error([$this->user->getId(), $this->type], 'HabitEmailSendWrongType');
        }
        return $added;
    }

    /**
     * Returns available placeholders for email
     * @param string $type
     * @return array
     */
    public function getEmailPlaceholders(string $type): array
    {
        $args = [
            'name' => $this->user->name,
        ];
        $blogs = $this->getBlogs();
        // get key of blogs array fro slug
        $blog_key = 0;
        foreach (static::TYPE_BEFORE_MINS as $key => $value) {
            if ($key == $type) {
                break;
            }
            $blog_key++;
        }

        $blog_slug = $blogs[$blog_key] ?? null;
        if ($blog_slug) {
            $args['url'] = Url::toPublic(['blog-post/view', 'slug' => $blog_slug], true, true, $this->user->language);
        } else {
            $args['url'] = Url::toPublic(['blog-post/index'], true, true, $this->user->language);
            Yii::error([$this->user->getId(), $type, $blogs], 'HabitEmailBlogNotFound');
        }

        return $args;
    }

    /**
     * Get sorted blogs
     * @return array|null
     */
    public function getBlogs()
    {
        if ($this->blogs === null) {
            $blogs_array = [];
            $blogs = BlogPost::getByCategory(BlogPost::CATEGORY_HABITS, ['slug', 'published_at']);
            if ($blogs) {
                foreach ($blogs as $blog) {
                    $published_at = $blog->published_at ? $blog->published_at->toDateTime()->getTimestamp() : null;
                    if ($published_at) {
                        $blogs_array[$published_at] = $blog->slug;
                    }
                }
                ksort($blogs_array);
                $blogs_array = array_values($blogs_array);
                $this->blogs = $blogs_array;
            } else {
                Yii::error([$this->user->getId()], 'HabitEmailsEmptyBlogs');
            }
        } else {
            $blogs_array = $this->blogs;
        }

        return $blogs_array;
    }

}
