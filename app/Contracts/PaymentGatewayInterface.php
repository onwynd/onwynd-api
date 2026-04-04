<?php

namespace App\Contracts;

use Illuminate\Http\Request;

interface PaymentGatewayInterface
{
    /**
     * Initiate a payment transaction.
     *
     * @param  array  $payload  [amount, email, currency, reference, metadata]
     * @return array [success, reference, authorization_url, access_code, message]
     */
    public function initiate(array $payload): array;

    /**
     * Verify a completed payment.
     *
     * @return array [success, status, amount, currency, reference, metadata]
     */
    public function verify(string $reference): array;

    /**
     * Handle incoming webhook from gateway.
     */
    public function handleWebhook(Request $request): void;

    /**
     * Create a transfer recipient (for payouts).
     *
     * @param  array  $bankDetails  [account_number, bank_code, account_name]
     * @return string The recipient code/ID
     */
    public function createTransferRecipient(array $bankDetails): string;

    /**
     * Initiate a payout transfer.
     *
     * @param  array  $payload  [amount, recipient_code, reason, reference]
     * @return array [success, transfer_code, message]
     */
    public function transfer(array $payload): array;

    /**
     * Whether this gateway supports the given currency.
     */
    public function supports(string $currency): bool;

    /**
     * Human-readable name.
     */
    public function getName(): string;
}
