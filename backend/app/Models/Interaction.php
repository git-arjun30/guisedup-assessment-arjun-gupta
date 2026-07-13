<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Interaction extends Model
{
    /** @use HasFactory<\Database\Factories\InteractionFactory> */
    use HasFactory;

    public const TYPE_VIEW = 'view';

    public const TYPE_REACTION = 'reaction';

    public const TYPE_REPLY = 'reply';

    public const TYPES = [
        self::TYPE_VIEW,
        self::TYPE_REACTION,
        self::TYPE_REPLY,
    ];

    protected $fillable = [
        'user_id',
        'post_id',
        'type',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }
}
