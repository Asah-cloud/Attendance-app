<?php

namespace App\Models\Concerns;

use App\Models\Company;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Payment settings shared by custom forms (payment_*) and the registration form on an event
 * (registration_payment_*). The manager sets the amount and can change it at any time; with
 * "payer sets amount" people may pay more, or any amount down to the minimum. With priced
 * choices ("Adult: 200") people pick one and pay its price instead.
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
            && ($this->paymentSetting('amount_minor') || $this->paymentSetting('payer_sets_amount') || $this->paymentOptions());
    }

    /**
     * The priced choices people pick from, if any.
     *
     * @return list<array{label: string, amount_minor: int}>
     */
    public function paymentOptions(): array
    {
        return array_values($this->paymentSetting('options') ?? []);
    }

    public function paymentOptionAmount(?string $label): ?int
    {
        return collect($this->paymentOptions())->firstWhere('label', $label)['amount_minor'] ?? null;
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
        return (bool) $this->paymentSetting('payer_sets_amount') && ! $this->paymentOptions();
    }

    /** The least someone may pay when they set the amount themselves (at least GH₵ 1). */
    public function paymentMinimumMinor(): int
    {
        return max(100, (int) $this->paymentSetting('minimum_minor'));
    }

    /** Rules for what people fill in: the choice they're paying for, or the amount box (in cedis). */
    public function paymentAmountRules(): array
    {
        return match (true) {
            (bool) $this->paymentOptions() => ['payment_option' => ['required', Rule::in(array_column($this->paymentOptions(), 'label'))]],
            $this->payerSetsAmount() => ['payment_amount' => ['required', 'numeric', 'min:'.($this->paymentMinimumMinor() / 100), 'max:1000000']],
            default => [],
        };
    }

    /** What to charge, in pesewas: the chosen option's price, what the person typed, or the set amount. */
    public function chargeFor(array $validated): int
    {
        return match (true) {
            (bool) $this->paymentOptions() => (int) $this->paymentOptionAmount($validated['payment_option'] ?? null),
            $this->payerSetsAmount() => (int) round(((float) ($validated['payment_amount'] ?? 0)) * 100),
            default => $this->paymentAmountMinor(),
        };
    }

    public static function paymentSettingRules(): array
    {
        return [
            'payment_enabled' => ['nullable', 'boolean'],
            'payment_label' => ['nullable', 'string', 'max:100'],
            'payment_amount' => ['nullable', 'numeric', 'min:1', 'max:1000000'],
            'payment_options' => ['nullable', 'string', 'max:3000'],
            'payment_payer_sets_amount' => ['nullable', 'boolean'],
            'payment_minimum' => ['nullable', 'numeric', 'min:1', 'max:1000000'],
        ];
    }

    /**
     * Priced choices typed one per line as "Adult: 200" (or "Adult - 200"), in cedis.
     *
     * @return list<array{label: string, amount_minor: int}>
     *
     * @throws ValidationException naming the first line that isn't a choice and price
     */
    public static function parsePaymentOptions(?string $text): array
    {
        $options = [];
        foreach (preg_split('/\r\n|\r|\n/', (string) $text) as $line) {
            if (trim($line) === '') {
                continue;
            }
            if (! preg_match('/^\s*(.+?)\s*[:\-–=]\s*(?:GH₵|GHS|GHC|₵)?\s*([0-9]+(?:\.[0-9]{1,2})?)\s*$/iu', $line, $match) || (float) $match[2] < 1) {
                throw ValidationException::withMessages(['payment_options' => 'Write each choice as "Name: price", e.g. "Adult: 200". Check: "'.trim($line).'".']);
            }
            $options[mb_strtolower($match[1])] = ['label' => mb_substr($match[1], 0, 100), 'amount_minor' => (int) round(((float) $match[2]) * 100)];
        }

        return array_values($options);
    }

    /** The choices as the manager typed them, for the settings box. */
    public function paymentOptionsText(): string
    {
        return collect($this->paymentOptions())->map(fn ($option) => $option['label'].': '.rtrim(rtrim(number_format($option['amount_minor'] / 100, 2, '.', ''), '0'), '.'))->implode("\n");
    }

    /** The validated payment_* inputs as this model's columns. */
    public function paymentColumns(array $validated): array
    {
        $minor = fn ($value) => filled($value) ? (int) round(((float) $value) * 100) : null;
        $prefix = $this->paymentPrefix();
        $options = self::parsePaymentOptions($validated['payment_options'] ?? null);

        return [
            $prefix.'options' => $options ?: null,
            $prefix.'enabled' => (bool) ($validated['payment_enabled'] ?? false),
            $prefix.'label' => $validated['payment_label'] ?? null,
            $prefix.'amount_minor' => $minor($validated['payment_amount'] ?? null),
            $prefix.'payer_sets_amount' => (bool) ($validated['payment_payer_sets_amount'] ?? false),
            $prefix.'minimum_minor' => $minor($validated['payment_minimum'] ?? null),
        ];
    }
}
