<?php

namespace App\Jobs;

use App\Helper\FirebaseService;
use App\Helper\Whatsapp;
use App\Helper\WhatsappComponent;
use App\Models\Event;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Auth;

class SendReminder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $method;

    protected $event;

    protected $sendToOwner;

    protected ?string $notificationId = null;

    /**
     * Create a new job instance.
     */
    public function __construct($method, $event, $sendToOwner = false)
    {

        $this->method = $method;
        $this->event = Event::with(['employees', 'packages', 'products', 'typeEvent'])->find($event->id);
        $this->sendToOwner = $sendToOwner;
        $this->notificationId = (string) \Illuminate\Support\Str::uuid();
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        switch ($this->method) {
            case 'sms':
                $this->sendSms();
                break;
            case 'email':
                $this->sendEmail();
                break;
            case 'whatsapp':
                $this->sendWhatsapp();
                break;
            case 'pushNotification':
                logger('Sending push notification...');
                $this->sendPushNotification();
                break;
            default:
                break;
        }
    }

    private function sendSms() {}

    private function sendEmail() {}

    private function sendWhatsapp()
    {

        $event = $this->event;

        $eventType = $event['typeEvent']['name'];
        // get event date and time
        $eventDate = date('d/m/Y', strtotime($event->event_date));
        $eventTime = date('H:i', strtotime($event->event_date));
        $eventAddress = $event->event_address;
        if ($event->employees->count() >= 1) {
            $eventCoordinator = $event->employees->first()->name;
        } else {
            $eventCoordinator = 'No asignado';
        }
        $eventComments = $event->notes ?? 'N/A';
        if ($this->sendToOwner) {
            $user = Auth::user();
            if ($user == null) {
                $phoneOwner = '3121034666';
                $phone = "52$phoneOwner";
            } else {
                $phoneOwner = Auth::user()->phone;
                $phone = "52$phoneOwner";
            }
            // Verify if event commnts is empty
            if ($eventComments == '') {
                $eventComments = 'N/A';
            }

            $response = Whatsapp::templateMessage($phone)
                ->setName('pirotecnia_san_rafael_reminder')
                ->setLanguage('es')
                ->addComponent(WhatsappComponent::bodyComponent()
                    ->addParameter('text', $eventType ?? 'Otro', null)
                    ->addParameter('text', $eventDate ?? 'N/A', null)
                    ->addParameter('text', $eventTime ?? '00:00', null)
                    ->addParameter('text', $eventAddress ?? 'N/A', null)
                    ->addParameter('text', $eventCoordinator ?? 'N/A', null)
                    ->addParameter('text', $eventComments ?? 'N/A', null))
                ->addComponent(WhatsappComponent::buttonComponent()
                    ->setSubType('url')
                    ->setIndex('0')
                    ->addParameter('text', "$event->id", null))
                ->send();
        } else {
            foreach ($event->employees as $employee) {
                if ($employee->pivot->is_send_message == 0) {
                    $phoneEmployee = $employee->phone;
                    $phone = "52$phoneEmployee";

                    $response = Whatsapp::templateMessage($phone)
                        ->setName('pirotecnia_san_rafael_reminder')
                        ->setLanguage('es')
                        ->addComponent(WhatsappComponent::bodyComponent()
                            ->addParameter('text', $eventType ?? 'Otro', null)
                            ->addParameter('text', $eventDate ?? 'N/A', null)
                            ->addParameter('text', $eventTime ?? '00:00', null)
                            ->addParameter('text', $eventAddress ?? 'N/A', null)
                            ->addParameter('text', $eventCoordinator ?? 'N/A', null)
                            ->addParameter('text', $eventComments ?? 'N/A', null))
                        ->addComponent(WhatsappComponent::buttonComponent()
                            ->setSubType('url')
                            ->setIndex('0')
                            ->addParameter('text', "$event->id", null))
                        ->send();

                    $event->employees()->updateExistingPivot($employee->id, ['is_send_message' => 1]);
                } else {
                }
            }
        }
    }

    private function sendPushNotification()
    {
        $event = $this->event;
        $recipient = $event->employees->first()?->user;
        if (! $recipient) {
            return;
        }
        $token = $recipient->fcm_token;
        // $token = 'cAyzVSqTT0kfrtjoFKQctq:APA91bEzA6YjIG5QvCzHsSXii_5OB6dvABetSE6tFykgtp_QyTmvTSrHs0EEzjdwvDCqTnXQdvb4v6DXwgDPfOgDdmRmdhFohXVXOa5XQ1bhzuM8kEQcO2g';

        $notification = [
            'title' => 'Pirotecnia San Rafael - Recordatorio de Evento',
            'body' => $event->event_address.' - '.$event->event_date,
        ];
        $this->notificationId ??= (string) \Illuminate\Support\Str::uuid();
        app(\App\Services\Mobile\MobileNotificationService::class)->remember($recipient, $this->notificationId, $notification['title'], $notification['body'], $event->id);
        if (! $token) {
            return;
        }

        $data = [
            'event_id' => $event->id,
            'user_id' => $recipient->id,
            'notification_id' => $this->notificationId,
        ];

        $apns = [
            'headers' => [
                'apns-priority' => '10',
            ],
            'payload' => [
                'aps' => [
                    'alert' => [
                        'title' => $notification['title'] ?? 'Notificación',
                        'body' => $notification['body'] ?? 'Este es un ejemplo de notificación',
                    ],
                    'sound' => 'default',
                ],
            ],
        ];

        $firebaseService = new FirebaseService;

        // Send push notification using your preferred service
        $firebaseService->sendMessage($token, $notification, $data, $apns);
    }
}
