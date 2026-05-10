<?php

namespace App\Infrastructure\Persistence\Configuration\Eloquent;

use Database\Factories\MailTemplateModelFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MailTemplateModel extends Model
{
    /** @use HasFactory<\Database\Factories\MailTemplateModelFactory> */
    use HasFactory;

    protected $table = 'mail_templates';

    protected $fillable = [
        'template_key',
        'description',
        'subject',
        'body_html',
        'body_text',
        'is_active',
        'updated_by_user_id',
    ];

    protected $casts = [
        'is_active' => 'bool',
        'updated_by_user_id' => 'integer',
    ];

    protected static function newFactory(): MailTemplateModelFactory
    {
        return MailTemplateModelFactory::new();
    }
}
