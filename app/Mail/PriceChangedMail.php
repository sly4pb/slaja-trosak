<?php

namespace App\Mail;

use App\Models\TrackedProduct;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PriceChangedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public TrackedProduct $product,
        public float $oldPrice,
        public float $newPrice,
    ) {}

    public function envelope(): Envelope
    {
        $direction = $this->newPrice < $this->oldPrice ? 'Price drop' : 'Price increase';

        return new Envelope(
            subject: "{$direction}: {$this->product->product_name}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.price-changed',
        );
    }
}
