<?php

namespace App\Infrastructure\Persistence\Configuration\Eloquent;

use App\Domain\Configuration\Contracts\MailTemplateRepository;
use App\Domain\Configuration\MailTemplate;
use App\Domain\Shared\ValueObjects\Identifier;
use App\Support\Persistence\CastsDateTime;
use DateTimeImmutable;

final class EloquentMailTemplateRepository implements MailTemplateRepository
{
    use CastsDateTime;

    public function nextIdentity(): Identifier
    {
        $next = (int) (MailTemplateModel::query()->max('id') ?? 0) + 1;

        return Identifier::fromInt($next);
    }

    public function getByKey(string $templateKey): ?MailTemplate
    {
        $model = MailTemplateModel::query()->where('template_key', trim($templateKey))->first();

        return $model ? $this->mapModel($model) : null;
    }

    public function save(MailTemplate $template): void
    {
        $model = MailTemplateModel::find($template->id()->toInt()) ?? new MailTemplateModel(['id' => $template->id()->toInt()]);

        $model->template_key = $template->templateKey();
        $model->description = $template->description();
        $model->subject = $template->subject();
        $model->body_html = $template->bodyHtml();
        $model->body_text = $template->bodyText();
        $model->is_active = $template->isActive();
        $model->updated_by_user_id = $template->updatedByUserId();
        $model->save();
    }

    public function delete(Identifier $id): void
    {
        MailTemplateModel::query()->whereKey($id->toInt())->delete();
    }

    public function all(): iterable
    {
        return MailTemplateModel::query()
            ->orderBy('template_key')
            ->get()
            ->map(fn (MailTemplateModel $model) => $this->mapModel($model));
    }

    private function mapModel(MailTemplateModel $model): MailTemplate
    {
        return MailTemplate::hydrate(
            Identifier::fromInt((int) $model->getKey()),
            $model->template_key,
            $model->description,
            $model->subject,
            $model->body_html,
            $model->body_text,
            (bool) $model->is_active,
            $model->updated_by_user_id !== null ? (int) $model->updated_by_user_id : null,
            $this->toImmutable($model->created_at) ?? new DateTimeImmutable,
            $this->toImmutable($model->updated_at) ?? new DateTimeImmutable,
        );
    }
}
