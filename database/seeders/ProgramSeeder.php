<?php

namespace Database\Seeders;

class ProgramSeeder extends CatalogReferenceSeeder
{
    public function run(?string $file = null): array
    {
        return $this->seedReferences('programs', ['description'], $file);
    }
}
