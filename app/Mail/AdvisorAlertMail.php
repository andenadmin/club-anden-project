<?php

namespace App\Mail;

use App\Models\BotSession;
use App\Models\Cliente;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AdvisorAlertMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $nombreCliente;
    public string $numeroContacto;
    public string $motivoLabel;
    public string $inboxUrl;
    public string $ramaActiva;

    public function __construct(BotSession $session, string $motivo)
    {
        $cliente = Cliente::find($session->id_cliente);

        $this->nombreCliente  = $cliente?->nombre_cliente ?? 'Desconocido';
        $this->numeroContacto = $session->numero_contacto;
        $this->motivoLabel    = $this->labelForMotivo($motivo);
        $this->ramaActiva     = $session->rama_activa ?? '—';
        $this->inboxUrl       = url('/inbox/' . $session->numero_contacto);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Andy Bot — Asesor requerido: {$this->nombreCliente}",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.advisor-alert',
        );
    }

    private function labelForMotivo(string $motivo): string
    {
        return match($motivo) {
            'SOLICITUD_CLIENTE'             => 'El cliente solicitó hablar con un asesor',
            'OPCIONES_INVALIDAS_REITERADAS' => 'Opciones inválidas reiteradas (2 errores seguidos)',
            'CAPACIDAD_EXCEDIDA'            => 'Capacidad excedida (más de 50 niños)',
            default                         => $motivo,
        };
    }
}
