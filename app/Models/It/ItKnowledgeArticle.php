<?php

namespace App\Models\It;

use App\Models\Core\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItKnowledgeArticle extends Model
{
    protected $table = 'it_knowledge_articles';

    protected $fillable = [
        'title',
        'category',
        'body',
        'audience',
        'is_published',
        'views',
        'author_id',
    ];

    protected $casts = [
        'is_published' => 'boolean',
    ];

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function scopePublished($query)
    {
        return $query->where('is_published', true);
    }
}
