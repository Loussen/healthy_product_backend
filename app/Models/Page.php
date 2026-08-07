<?php

namespace App\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Cviebrock\EloquentSluggable\Sluggable;
use Cviebrock\EloquentSluggable\SluggableScopeHelpers;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Backpack\CRUD\app\Models\Traits\SpatieTranslatable\HasTranslations;

class Page extends Model
{
    use CrudTrait;
    use Sluggable;
    use SluggableScopeHelpers;
    use HasTranslations;

    /*
    |--------------------------------------------------------------------------
    | GLOBAL VARIABLES
    |--------------------------------------------------------------------------
    */

    protected $table = 'pages';
    protected $primaryKey = 'id';
    public $timestamps = true;
    // protected $guarded = ['id'];
    protected $fillable = ['template', 'name', 'title', 'slug', 'content'];
    // protected $hidden = [];
    // protected $dates = [];
//    protected $casts = [
//        'extras' => 'array',
//    ];

    public $translatable = ['title', 'content'];

    /**
     * Return the sluggable configuration array for this model.
     *
     * @return array
     */
    public function sluggable(): array
    {
        return [
            'slug' => [
                'source' => 'slug_or_title',
            ],
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | FUNCTIONS
    |--------------------------------------------------------------------------
    */

    public function getTemplateName()
    {
        return str_replace('_', ' ', Str::title($this->template));
    }

    public function getPageLink()
    {
        return url($this->slug);
    }

    public function getOpenButton()
    {
        return '<a class="btn btn-sm btn-link" href="'.$this->getPageLink().'" target="_blank">'.
            '<i class="la la-eye"></i> '.trans('backpack::pagemanager.open').'</a>';
    }

    /*
    |--------------------------------------------------------------------------
    | RELATIONS
    |--------------------------------------------------------------------------
    */

    /*
    |--------------------------------------------------------------------------
    | SCOPES
    |--------------------------------------------------------------------------
    */

    public function scopeBlogArticles($query)
    {
        return $query->where('template', 'blog_article');
    }

    public function isBlogArticle(): bool
    {
        return $this->template === 'blog_article';
    }

    public function getExcerpt(int $limit = 160): string
    {
        return \Illuminate\Support\Str::limit(strip_tags($this->content), $limit);
    }

    /**
     * Local cover at public/assets/images/blog/{slug}.jpg (or .webp/.png).
     */
    public function getCoverImagePath(): ?string
    {
        foreach (['jpg', 'jpeg', 'webp', 'png'] as $ext) {
            $relative = "assets/images/blog/{$this->slug}.{$ext}";
            if (is_file(public_path($relative))) {
                return $relative;
            }
        }

        return null;
    }

    public function getCoverImageUrl(): string
    {
        $path = $this->getCoverImagePath();

        return $path
            ? asset($path)
            : asset('assets/images/graphic.jpeg');
    }

    public function hasCoverImage(): bool
    {
        return $this->getCoverImagePath() !== null;
    }

    /*
    |--------------------------------------------------------------------------
    | ACCESORS
    |--------------------------------------------------------------------------
    */

    // The slug is created automatically from the "name" field if no slug exists.
    public function getSlugOrTitleAttribute()
    {
        if ($this->slug != '') {
            return $this->slug;
        }

        return $this->title;
    }

    /*
    |--------------------------------------------------------------------------
    | MUTATORS
    |--------------------------------------------------------------------------
    */
}
