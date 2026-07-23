<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * 🎉 ORDER PLACED EVENT
 * Triggered when a new order is created
 * → Sends WhatsApp confirmation
 * → Updates stock
 * → Sends confirmation email
 */
class OrderPlaced
{
    use Dispatchable, SerializesModels;

    public $order;
    public $user;

    public function __construct($order, $user = null)
    {
        $this->order = $order;
        $this->user = $user ?? auth()->user();
    }
}

/**
 * 💳 PAYMENT PROCESSED EVENT
 */
class PaymentProcessed
{
    use Dispatchable, SerializesModels;

    public $payment;
    public $order;

    public function __construct($payment, $order = null)
    {
        $this->payment = $payment;
        $this->order = $order;
    }
}

/**
 * 📦 ORDER SHIPPED EVENT
 */
class OrderShipped
{
    use Dispatchable, SerializesModels;

    public $order;

    public function __construct($order)
    {
        $this->order = $order;
    }
}

/**
 * ⭐ REVIEW SUBMITTED EVENT
 */
class ReviewSubmitted
{
    use Dispatchable, SerializesModels;

    public $review;

    public function __construct($review)
    {
        $this->review = $review;
    }
}

/**
 * 👤 USER REGISTERED EVENT
 */
class UserRegistered
{
    use Dispatchable, SerializesModels;

    public $user;

    public function __construct($user)
    {
        $this->user = $user;
    }
}

/**
 * ⚠️ LOW STOCK EVENT
 */
class LowStockAlert
{
    use Dispatchable, SerializesModels;

    public $product;
    public $quantity;

    public function __construct($product, $quantity = 10)
    {
        $this->product = $product;
        $this->quantity = $quantity;
    }
}
