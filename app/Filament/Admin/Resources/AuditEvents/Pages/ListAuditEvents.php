<?php

namespace App\Filament\Admin\Resources\AuditEvents\Pages;

use App\Filament\Admin\Resources\AuditEvents\AuditEventResource;
use Filament\Resources\Pages\ListRecords;

class ListAuditEvents extends ListRecords
{
    protected static string $resource = AuditEventResource::class;
}
