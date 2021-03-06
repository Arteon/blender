<?php

namespace App\Services\Html;

use Html;
use Carbon\Carbon;
use App\Models\Tag;
use App\Models\ContentBlock;
use Illuminate\Database\Eloquent\Model;
use Collective\Html\FormBuilder as BaseFormBuilder;
use Spatie\Blender\Model\Transformers\MediaTransformer;
use Spatie\Blender\Model\Transformers\ContentBlockTransformer;

class FormBuilder extends BaseFormBuilder
{
    public function openDraftable(array $options, Model $subject): string
    {
        $identifier = class_basename($subject).'_'.($subject->isDraft() ? 'new' : $subject->id);

        $options = array_merge($options, [
            'data-autosave' => '',
            'name' => $identifier,
            'id' => $identifier,
        ]);

        return $this->open($options);
    }

    public function openButton(array $formOptions = [], array $buttonOptions = []): string
    {
        if (strtolower($formOptions['method'] ?? '') === 'delete') {
            $formOptions['data-confirm'] = 'true';
        }

        return $this->open($formOptions).substr(el('button', $buttonOptions, ''), 0, -strlen('</button>'));
    }

    public function closeButton(): string
    {
        return '</button>'.$this->close();
    }

    public function redactor($subject, string $fieldName, string $locale = '', array $options = []): string
    {
        $initial = $this->useInitialValue($subject, $fieldName, $locale);
        $fieldName = $locale ? translate_field_name($fieldName, $locale) : $fieldName;

        return $this->textarea(
            $fieldName,
            $initial,
            array_merge($options, [
                'data-editor',
                'data-editor-medialibrary-url' => action(
                    'Back\Api\MediaLibraryController@add',
                    [short_class_name($subject), $subject->id, 'redactor']
                ),
            ])
        );
    }

    public function checkboxWithLabel($subject, string $fieldName, string $label, array $options = []): string
    {
        $options = array_merge(['class' => 'form-control'], $options);

        return el(
            'label.-checkbox',
            $this->checkbox($fieldName, 1, $this->useInitialValue($subject, $fieldName), $options)
            .' '.$label
        );
    }

    public function datePicker(string $name, string $value): string
    {
        return $this->text($name, $value, [
            'data-datetimepicker',
            'class' => '-datetime',
        ]);
    }

    public function tags($subject, string $type, array $options = []): string
    {
        $tags = Tag::getWithType($type)->pluck('name', 'name')->toArray();
        $subjectTags = $subject->tagsWithType($type)->pluck('name', 'name')->toArray();

        $options = array_merge(['multiple', 'data-select' => 'tags'], $options);

        return $this->select("{$type}_tags[]", $tags, $subjectTags, $options);
    }

    public function category($subject, $type, array $options = []): string
    {
        $categories = Tag::getWithType($type)->pluck('name', 'name')->toArray();
        $subjectCategory = $subject->tagsWithType($type)->first()->name ?? null;

        return $this->select("{$type}_tags[]", $categories, $subjectCategory, $options);
    }

    public function locales(array $locales, string $current): string
    {
        $list = array_reduce($locales, function (array $list, string $locale) {
            $list[$locale] = trans("locales.{$locale}");

            return $list;
        }, []);

        return $this->select('locale', $list, $current, ['data-select' => 'select']);
    }

    public function media($subject, string $collection, string $type, $associated = []): string
    {
        $initialMedia = fractal()
            ->collection($subject->getMedia($collection))
            ->transformWith(new MediaTransformer())
            ->toJson();

        $model = collect([
            'name' => get_class($subject),
            'id' => $subject->id,
        ])->toJson();

        return el('blender-media', [
            'collection' => $collection,
            'type' => $type,
            'upload-url' => action('Back\Api\MediaLibraryController@add'),
            ':model' => htmlspecialchars($model),
            ':initial' => htmlspecialchars($initialMedia),
            ':data' => htmlspecialchars($this->getAssociatedData($associated)),
            ':debug' => htmlspecialchars(json_encode(config('app.debug', false))),
        ], '');
    }

    public function contentBlocks(Model $subject, string $collectionName, string $editor, array $associated = []): string
    {
        $initialContentBlocks = fractal()
            ->collection($subject->getContentBlocks($collectionName))
            ->transformWith(new ContentBlockTransformer())
            ->toJson();

        $model = collect([
            'name' => get_class($subject),
            'id' => $subject->id,
        ])->toJson();

        $associatedData = $this->getAssociatedData(array_merge($associated, [
            'locales' => config('app.locales'),
            'contentLocale' => content_locale(),
            'mediaModel' => ContentBlock::class,
            'mediaUploadUrl' => action('Back\Api\MediaLibraryController@add'),
        ]));

        return el('blender-content-blocks', [
            'collection' => $collectionName,
            'editor' => $editor,
            'create-url' => action('Back\Api\ContentBlockController@add'),
            ':model' => htmlspecialchars($model),
            ':input' => htmlspecialchars($initialContentBlocks),
            ':data' => htmlspecialchars($associatedData),
            ':debug' => htmlspecialchars(json_encode(config('app.debug', false))),
        ], '');
    }

    protected function getAssociatedData($associated = []): string
    {
        $associated = collect($associated);

        $associated->put('locales', config('app.locales'));
        $associated->put('contentLocale', content_locale());

        return $associated->toJson();
    }

    public function useInitialValue($subject, string $propertyName, string $locale = ''): string
    {
        $fieldName = $locale ? translate_field_name($propertyName, $locale) : $propertyName;
        $value = $locale ? $subject->translate($propertyName, $locale) : $subject->$propertyName;

        if ($value instanceof Carbon) {
            $value = $value->format('d/m/Y');
        }

        return $this->getValueAttribute($fieldName, $value) ?? '';
    }

    public function getLabelForTranslatedField(string $fieldName, string $label, string $locale): string
    {
        return Html::decode(
            $this->label($fieldName, $label.el('span.label__lang', $locale))
        );
    }
}
