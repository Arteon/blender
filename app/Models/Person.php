<?php

namespace App\Models;

use Spatie\Blender\Model\Model;
use Spatie\EloquentSortable\Sortable;
use Spatie\Blender\Model\Traits\HasSlug;
use Spatie\EloquentSortable\SortableTrait;

class Person extends Model implements Sortable
{
    use HasSlug, SortableTrait;

    protected $with = ['media'];

    public $translatable = ['text'];

    protected $mediaLibraryCollections = ['images'];

    public function registerMediaConversions()
    {
        parent::registerMediaConversions();

        $this->addMediaConversion('thumb')
            ->width(368)
            ->height(232)
            ->performOnCollections('images');
    }

    protected function generateSlug(): string
    {
        return str_slug($this->name);
    }
}
