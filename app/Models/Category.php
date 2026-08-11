<?php

namespace App\Models;

use App\Traits\HasSlug;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Category extends Model
{
    use HasFactory, HasSlug;

    public const DEFAULT_IMAGE = 'default-category.svg';

    protected $guarded = [];

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    protected function image(): Attribute
    {
        return Attribute::make(
            get: fn($image) => empty($image) || $image === self::DEFAULT_IMAGE
                ? asset('images/category-default.svg')
                : asset('storage/categories/' . $image),
        );
    }
}
