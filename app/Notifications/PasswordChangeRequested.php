<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PasswordChangeRequested extends Notification
{
    use Queueable;

    public function __construct(private string $url) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Confirmar alteração de password — Bubbles')
            ->greeting('Olá, ' . $notifiable->name . '!')
            ->line('Recebemos um pedido para alterar a password da tua conta Bubbles.')
            ->action('Confirmar alteração de password', $this->url)
            ->line('Este link expira em 15 minutos.')
            ->line('Se não foste tu a fazer este pedido, ignora este email. A tua password não será alterada.');
    }
}
