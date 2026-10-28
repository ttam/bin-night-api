<?php

declare(strict_types=1);

namespace App\Models\Concerns;

trait ReleasesNameOnDelete
{
    /** 255 minus uuid and hyphen */
    private const int DELETED_NAME_LENGTH = 218;

    public function deleteReleasingName(): void
    {
        $this->getConnection()->transaction(function (): void {
            /** @var string $id */
            $id = $this->getKey();

            $deletedName = $this->name
                |> (static fn (string $name): string => \mb_substr($name, 0, self::DELETED_NAME_LENGTH))
                |> (static fn (string $name): string => \sprintf('%s-%s', $id, $name));

            $this->setAttribute('name', $deletedName);
            $this->save();
            $this->delete();
        });
    }
}
