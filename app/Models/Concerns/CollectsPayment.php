<?php

namespace App\Models\Concerns;

use App\Models\Company;

/**
 * Payment settings shared by custom forms (payment_*) and the registration form on an event
 * (registration_payment_*). The manager sets the amount and can change it at any time; with
 * "payer sets amount" people may pay more, or any amount down to the minimum.
 */
trait CollectsPayment
{
    abstract protected function paymentPrefix(): string;

    abstract public function paymentCompany(): ?Company;

    private function paymentSetting(string $key): mixed
    {
        return $this->getAttribute($this->paymentPrefix().$key);
    }

    /** Whether people are asked to pay: switched on, and the organisation can be paid out. */
    public function collectsPayment(): bool
    {
        return (bool) $this->paymentSetting('enabled') && filled($this->paymentCompany()?->payout_subaccount_code)
            && ($this->paymentSetting('amount_minor') || $this->paymentSetting('payer_sets_amount'));
    }

    public function paymentLabel(): string
    {
        return $this->paymentSetting('label') ?: 'Payment';
    }

    public function paymentAmountMinor(): int
    {
        return (int) $this->paymentSetting('amount_minor');
    }

    public function payerSetsAmount(): bool
    {
        return (bool) $this->paymentSetting('payer_sets_amount');
    }

    /** The least someone may pay when they set the amount themselves (at least GH₵ 1). */
    public function paymentMinimumMinor(): int
    {
        return max(100, (int) $this->paymentSetting('minimum_minor'));
    }

    /** Rules for the amount box people fill in, in cedis. */
    public function paymentAmountRules(): array
    {
        return $this->payerSetsAmount()
            ? ['payment_amount' => ['required', 'numeric', 'min:'.($this->paymentMinimumMinor() / 100), 'max:1000000']]
            : [];
    }

    /** What to charge, in pesewas: the set amount, or what the person typed when they may choose. */
    public function chargeFor(?string $typedAmount): int
    {
        return $this->payerSetsAmount() ? (int) round(((float) $typedAmount) * 100) : $this->paymentAmountMinor();
    }

    public static function paymentSettingRules(): array
    {
        return [
            'payment_enabled' => ['nullable', 'boolean'],
            'payment_label' => ['nullable', 'string', 'max:100'],
            'payment_amount' => ['nullable', 'numeric', 'min:1', 'max:1000000', 'required_if_accepted:payment_enabled'],
            'payment_payer_sets_amount' => ['nullable', 'boolean'],
            'payment_minimum' => ['nullable', 'numeric', 'min:1', 'max:1000000'],
        ];
    }

    /** The validated payment_* inputs as this model's columns. */
    public function paymentColumns(array $validated): array
    {
        $minor = fn ($value) => filled($value) ? (int) round(((float) $value) * 100) : null;
        $prefix = $this->paymentPrefix();

        return [
            $prefix.'enabled' => (bool) ($validated['payment_enabled'] ?? false),
            $prefix.'label' => $validated['payment_label'] ?? null,
            $prefix.'amount_minor' => $minor($validated['payment_amount'] ?? null),
            $prefix.'payer_sets_amount' => (bool) ($validated['payment_payer_sets_amount'] ?? false),
            $prefix.'minimum_minor' => $minor($validated['payment_minimum'] ?? null),
        ];
    }
}
