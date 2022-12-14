<?php

namespace PhoenixLib\NovaNestedTreeAttachMany;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Laravel\Nova\Authorizable;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\ResourceRelationshipGuesser;
use Laravel\Nova\Http\Requests\NovaRequest;
use PhoenixLib\NovaNestedTreeAttachMany\Domain\Relation\RelationHandlerFactory;
use PhoenixLib\NovaNestedTreeAttachMany\Rules\ArrayRules;

class NestedTreeAttachManyField extends Field
{
    use Authorizable;

    private $resourceClass;
    private $resourceName;
    private $manyToManyRelationship;

    public $component = 'nova-nested-tree-attach-many';

    public $showOnIndex = false;

    public function __construct($name, $attribute = null, $resource = null, $parentId = null)
    {
        parent::__construct($name, $attribute);

        $resource = $resource ?? ResourceRelationshipGuesser::guessResource($name);

        $this->resource = $resource;

        $this->resourceClass = $resource;
        $this->resourceName = $resource::uriKey();
        $this->manyToManyRelationship = $this->attribute;

        $this->fillUsing(function($request, $model, $attribute, $requestAttribute) use($resource) {

            if($this->meta['useAsField'] === true)
            {
                $model->{$attribute} = json_decode($request->{$attribute}, true);
            }
            else if(is_subclass_of($model, 'Illuminate\Database\Eloquent\Model'))
            {
                $model::saved(function($model) use($attribute, $request) {

                    $factory = App::make(RelationHandlerFactory::class);

                    $handler = $factory->make($model->{$attribute}());

                    $handler->attach($model, $attribute, json_decode($request->{$attribute}, true));

                });
                unset($request->{$attribute});
            }
        });

        $this->withMeta([
            'idKey'             => 'id',
            'labelKey'          => 'name',
            'childrenKey'       => 'children',
            'activeKey'         => 'is_active',
            'multiple'          => true,
            'flatten'            => true,
            'searchable'        => true,
            'placeholder'       => __('Select Category'),
            'alwaysOpen'        => true,
            'sortValueBy'       => 'LEVEL',
            'disabled'          => false,
            'rtl'               => false,
            'maxHeight'         => 500,
            'isActiveFalse'     => false,
            'useAsField'        => false,
        ]);

        if(!App::make(NovaRequest::class)->isResourceIndexRequest()){
            /** @var Domain\Cache\Cache $requestCache */
            $forRequestCache = App::make(Domain\Cache\Cache::class);

            $tag = get_class($this->resourceClass::newModel());

            if(!$forRequestCache->has($tag))
            {
                $query = $this->resourceClass::buildIndexQuery(
                    App::make(NovaRequest::class), $this->resourceClass::newModel()->newQuery()
                );
                if($parentId) {
                    $forRequestCache->put($tag, $query->descendantsAndSelf($parentId)->toTree());
                } else {
                    $forRequestCache->put($tag, $query->get()->toTree());
                }
            }

            $this->withMeta([
                'options' => $forRequestCache->get($tag)
            ]);
        }
    }

    public function searchable(bool $searchable): NestedTreeAttachManyField
    {
        $this->withMeta([
            'searchable' => $searchable,
        ]);

        return $this;
    }

    public function withIdKey(string $idKey = 'id'): NestedTreeAttachManyField
    {
        $this->withMeta([
            'idKey' => $idKey,
        ]);

        return $this;
    }

    public function withLabelKey(string $labelKey = 'name'): NestedTreeAttachManyField
    {
        $this->withMeta([
            'labelKey' => $labelKey,
        ]);

        return $this;
    }

    public function withChildrenKey(string $childrenKey): NestedTreeAttachManyField
    {
        $this->withMeta([
            'childrenKey' => $childrenKey,
        ]);

        return $this;
    }

    public function withActiveKey(string $activeKey): NestedTreeAttachManyField
    {
        $this->withMeta([
            'activeKey' => $activeKey,
        ]);

        return $this;
    }


    public function withPlaceholder(string $placeholder): NestedTreeAttachManyField
    {
        $this->withMeta([
            'placeholder' => $placeholder,
        ]);

        return $this;
    }

    public function withMaxHeight(int $maxHeight): NestedTreeAttachManyField
    {
        $this->withMeta([
            'maxHeight' => $maxHeight,
        ]);

        return $this;
    }

    public function withAlwaysOpen(bool $alwaysOpen): NestedTreeAttachManyField
    {
        $this->withMeta([
            'alwaysOpen' => $alwaysOpen,
        ]);

        return $this;
    }

    public function withSortValueBy(string $sortBy): NestedTreeAttachManyField
    {
        $this->withMeta([
            'sortValueBy' => $sortBy,
        ]);

        return $this;
    }

    public function withFlatten(bool $flatten): NestedTreeAttachManyField
    {
        $this->withMeta([
            'flatten' => $flatten,
        ]);

        return $this;
    }

    public function isActiveFalseValue( $value = false ): NestedTreeAttachManyField
    {
        $this->withMeta([
            'isActiveFalse' => $value,
        ]);

        return $this;
    }

    public function useSingleSelect(): NestedTreeAttachManyField
    {
        $this->withMeta([
            'multiple' => false,
            'flatten' => false
        ]);

        return $this;
    }

    public function useAsField(): NestedTreeAttachManyField
    {
        $this->withMeta([
            'useAsField' => true,
        ]);

        return $this;
    }

    public function authorize(Request $request)
    {
        if(! $this->resourceClass::authorizable()) {
            return true;
        }

        if(! isset($request->resource)) {
            return false;
        }

        return call_user_func([ $this->resourceClass, 'authorizedToViewAny'], $request)
            && $request->newResource()->authorizedToAttachAny($request, $this->resourceClass::newModel())
            && parent::authorize($request);
    }

    public function rules($rules)
    {
        $rules = ($rules instanceof Rule || is_string($rules)) ? func_get_args() : (array)$rules;

        $this->rules = [ new ArrayRules($rules) ];

        return $this;
    }
}
