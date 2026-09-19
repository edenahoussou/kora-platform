<?php

namespace App\Modules\IdentityAccess\Actions;

use App\Models\User;
use App\Modules\IdentityAccess\Data\TerminalP501Status;
use App\Modules\IdentityAccess\Data\TerminalType;
use App\Modules\IdentityAccess\Models\TerminalP501;

class RegisterTerminalAction
{
    /**
     * @param  array{serial_number: string, type?: TerminalType, agency_id?: int|null}  $data
     */
    public function execute(array $data, User $actor): TerminalP501
    {
        $terminal = TerminalP501::query()->create([
            'serial_number' => $data['serial_number'],
            'type' => $data['type'] ?? TerminalType::P501,
            'agency_id' => $data['agency_id'] ?? null,
            'status' => TerminalP501Status::Active,
        ]);

        activity()
            ->performedOn($terminal)
            ->causedBy($actor)
            ->withProperties(['serial_number' => $terminal->serial_number, 'type' => $terminal->type->value])
            ->log('terminal_registered');

        return $terminal;
    }
}
