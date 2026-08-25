<?php

namespace App\Filament\Provider\Resources\ProviderStaffInvitationResource\Pages;

use App\Filament\Provider\Resources\ProviderStaffInvitationResource;
use App\Services\ProviderStaffInvitationService;
use App\Support\CurrentProviderCompany;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateProviderStaffInvitation extends CreateRecord
{
    protected static string $resource = ProviderStaffInvitationResource::class;

    private ?string $acceptUrl = null;

    protected function handleRecordCreation(array $data): Model
    {
        $invitation = app(ProviderStaffInvitationService::class)->invite(
            app(CurrentProviderCompany::class)->company(),
            $data['email'],
            $data['role'],
            auth()->user(),
        );
        $this->acceptUrl = route('provider-staff-invitations.show', ['token' => $invitation->plainTextToken]);

        return $invitation;
    }

    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('Staff invitation created')
            ->body('Share this private acceptance link with the invited person: '.$this->acceptUrl)
            ->persistent();
    }
}
