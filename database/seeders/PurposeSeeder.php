<?php

namespace Database\Seeders;

class PurposeSeeder extends CatalogReferenceSeeder
{
    public function run(?string $file = null): array
    {
        return $this->seedReferences('purposes', ['description', 'examples'], $file);
    }
}
