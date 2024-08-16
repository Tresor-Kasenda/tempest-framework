<?php

declare(strict_types=1);

namespace Tempest\Database;

use Tempest\Database\Builder\ModelQueryBuilder;
use Tempest\Database\Builder\TableName;
use Tempest\Database\Exceptions\MissingRelation;
use function Tempest\make;
use Tempest\Support\Reflection\ClassReflector;
use Tempest\Support\Reflection\PropertyReflector;

trait IsDatabaseModel
{
    public ?Id $id = null;

    public function __get(string $name)
    {
        $property = PropertyReflector::fromParts($this, $name);

        if ($property->hasAttribute(Lazy::class)) {
            $this->load($name);

            return $property->getValue($this);
        }

        throw new MissingRelation($this, $name);
    }

    public function setId(Id $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function getId(): Id
    {
        return $this->id;
    }

    public static function table(): TableName
    {
        return new TableName(pathinfo(str_replace('\\', '/', static::class), PATHINFO_FILENAME));
    }

    public static function new(mixed ...$params): self
    {
        return make(self::class)->from($params);
    }

    /**
     * @return \Tempest\Database\Builder\ModelQueryBuilder<self>
     */
    public static function query(): ModelQueryBuilder
    {
        return new ModelQueryBuilder(self::class);
    }

    /** @return self[] */
    public static function all(array $relations = []): array
    {
        return self::query()
            ->with(...$relations)
            ->all();
    }

    public static function find(Id $id, array $relations = []): ?self
    {
        return self::query()
            ->with(...$relations)
            ->find($id);
    }

    public function load(string ...$relations): self
    {
        $new = self::find($this->getId(), $relations);

        foreach ((new ClassReflector($new))->getPublicProperties() as $property) {
            $property->setValue($this, $property->getValue($new));
        }

        return $this;
    }

    public static function create(mixed ...$params): self
    {
        $model = self::new(...$params);

        $id = make(Query::class)->from($model)->execute();

        $model->setId($id);

        return $model;
    }

    public static function updateOrNew(array $find, array $update): self
    {
        $existing = self::query()->bind(...$find);

        foreach ($find as $key => $value) {
            $existing = $existing->where("{$key} = :{$key}");
        }

        $model = $existing->first() ?? self::new(...$find);

        foreach ($update as $key => $value) {
            $model->{$key} = $value;
        }

        return $model;
    }

    public static function updateOrCreate(array $find, array $update): self
    {
        return self::updateOrNew($find, $update)->save();
    }

    public function save(): self
    {
        $id = make(Query::class)->from($this)->execute();

        $this->setId($id);

        return $this;
    }

    public function update(mixed ...$params): self
    {
        foreach ($params as $key => $value) {
            $this->{$key} = $value;
        }

        $query = make(Query::class)->from($this);

        $query->execute();

        return $this;
    }

    // TODO: delete
}
