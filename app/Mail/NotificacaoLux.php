<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** E-mail no visual da LUX montado a partir de NotificacaoModelos::montar(). */
class NotificacaoLux extends Mailable
{
    /** @param array $anexos [['conteudo' => bytes, 'nome' => 'x.pdf', 'mime' => 'application/pdf'], ...] */
    public function __construct(public array $m, public array $anexos = [])
    {
    }

    public function attachments(): array
    {
        return array_map(
            fn ($a) => Attachment::fromData(fn () => $a['conteudo'], $a['nome'])->withMime($a['mime']),
            $this->anexos
        );
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->m['assunto']);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.lux.layout',
            text: 'emails.lux.texto',
            with: ['m' => $this->m],
        );
    }
}
