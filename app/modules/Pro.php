<?php
/**
 * Zinkly Social Lite - Pro membership. Plans are paid once and give Pro for a number of days (no stored cards, no webhooks,
 * no SDKs): Stripe Checkout and PayPal Checkout are reached with plain HTTPS calls, and a "custom" method (bank transfer,
 * cash, ...) waits for an admin to approve it. A payment is only marked paid after the server itself asked Stripe or PayPal.
 */
final class Pro
{
    /** Currencies without cents (Stripe and PayPal both expect whole numbers for these). */
    private const ZERO_DECIMAL = ['JPY', 'KRW', 'HUF', 'TWD', 'CLP', 'VND', 'ISK'];
    public const GATEWAYS = ['stripe', 'paypal', 'custom'];

    public static function enabled(): bool
    {
        return setting('pro_enabled', '0') === '1';
    }

    public static function isPro(?array $u): bool
    {
        return $u && !empty($u['pro_until']) && (int) $u['pro_until'] > time();
    }

    /** A small "PRO" badge for names (empty when Pro is off or the member is not Pro). */
    public static function badge(?array $u): string
    {
        return self::enabled() && setting('pro_badge', '1') === '1' && self::isPro($u)
            ? ' <span class="zl-pro-badge" title="' . e(t('pro.member')) . '">PRO</span>' : '';
    }

    /* ---------- plans ---------- */

    public static function plans(bool $activeOnly = true): array
    {
        return DB::all('SELECT * FROM {pro_plans}' . ($activeOnly ? ' WHERE active = 1' : '') . ' ORDER BY position, price_cents, id');
    }

    public static function plan(int $id, bool $activeOnly = true): ?array
    {
        return DB::one('SELECT * FROM {pro_plans} WHERE id = ?' . ($activeOnly ? ' AND active = 1' : ''), [$id]);
    }

    public static function cheapest(): ?array
    {
        $plans = self::plans();
        return $plans ? $plans[0] : null;
    }

    /** Save a plan from the admin form. $id 0 adds a new one. */
    public static function savePlan(int $id, array $in): void
    {
        $cur = strtoupper(preg_replace('/[^A-Za-z]/', '', (string) ($in['currency'] ?? 'EUR')));
        $row = [
            'name' => Text::clean((string) ($in['name'] ?? ''), 80) ?: 'Pro',
            'price_cents' => self::toCents((string) ($in['price'] ?? '0'), strlen($cur) === 3 ? $cur : 'EUR'),
            'currency' => strlen($cur) === 3 ? $cur : 'EUR',
            'period_days' => max(1, min(3650, (int) ($in['period_days'] ?? 30))),
            'perks' => Text::clean((string) ($in['perks'] ?? ''), 1000),
            'active' => !empty($in['active']) ? 1 : 0,
            'position' => (int) ($in['position'] ?? 0),
        ];
        if ($row['price_cents'] < 1) {
            throw new RuntimeException(t('pro.bad_price'));
        }
        if ($id) {
            DB::update('pro_plans', $row, 'id = ?', [$id]);
        } else {
            DB::insert('pro_plans', $row + ['created_at' => time()]);
        }
    }

    /* ---------- money ---------- */

    public static function toCents(string $price, string $currency): int
    {
        $v = (float) str_replace(',', '.', trim($price));
        return (int) round(in_array($currency, self::ZERO_DECIMAL, true) ? $v : $v * 100);
    }

    /** "4.99" (or "500" for JPY): the amount as the payment services expect it. */
    public static function amount(int $cents, string $currency): string
    {
        return in_array($currency, self::ZERO_DECIMAL, true) ? (string) $cents : number_format($cents / 100, 2, '.', '');
    }

    public static function money(int $cents, string $currency): string
    {
        $sym = ['EUR' => '€', 'USD' => '$', 'GBP' => '£', 'JPY' => '¥', 'INR' => '₹', 'BRL' => 'R$', 'RUB' => '₽'][$currency] ?? '';
        $num = in_array($currency, self::ZERO_DECIMAL, true) ? number_format($cents) : number_format($cents / 100, 2);
        return $sym !== '' ? $sym . $num : $num . ' ' . $currency;
    }

    /** "month", "year", "30 days" ... for "€4.99 / month". */
    public static function period(int $days): string
    {
        return match ($days) {
            7 => t('pro.per_week'), 30, 31 => t('pro.per_month'), 90 => t('pro.per_quarter'), 365, 366 => t('pro.per_year'),
            default => tc('pro.per_days', $days),
        };
    }

    /* ---------- payment methods ---------- */

    /** The payment methods that are switched on and fully set up. */
    public static function gateways(): array
    {
        $out = [];
        if (setting('pay_stripe', '0') === '1' && setting('stripe_secret', '') !== '') {
            $out[] = 'stripe';
        }
        if (setting('pay_paypal', '0') === '1' && setting('paypal_client', '') !== '' && setting('paypal_secret', '') !== '') {
            $out[] = 'paypal';
        }
        if (setting('pay_custom', '0') === '1') {
            $out[] = 'custom';
        }
        return $out;
    }

    public static function gatewayName(string $g): string
    {
        return $g === 'custom' ? ((string) setting('custom_title', '') ?: t('pro.gw_custom')) : t('pro.gw_' . $g);
    }

    /**
     * Start paying for a plan. Returns the address to send the member to (Stripe / PayPal), or null for the custom method
     * (the payment then waits for an admin).
     */
    public static function checkout(int $user, array $plan, string $gateway, string $note = ''): ?string
    {
        if (!in_array($gateway, self::gateways(), true)) {
            throw new RuntimeException(t('pro.gw_off'));
        }
        $pid = DB::insert('pro_payments', ['user_id' => $user, 'plan_id' => (int) $plan['id'], 'gateway' => $gateway, 'amount_cents' => (int) $plan['price_cents'],
            'currency' => $plan['currency'], 'days' => (int) $plan['period_days'], 'status' => 'pending', 'note' => Text::clean($note, 500), 'created_at' => time()]);
        try {
            if ($gateway === 'stripe') {
                $s = self::stripe('POST', 'checkout/sessions', [
                    'mode' => 'payment', 'client_reference_id' => (string) $pid, 'metadata[payment_id]' => (string) $pid,
                    'line_items[0][quantity]' => '1', 'line_items[0][price_data][currency]' => strtolower($plan['currency']),
                    'line_items[0][price_data][unit_amount]' => (string) (int) $plan['price_cents'],
                    'line_items[0][price_data][product_data][name]' => site_name() . ' ' . $plan['name'],
                    'success_url' => full_url('pro/return/stripe') . '?pid=' . $pid . '&sid={CHECKOUT_SESSION_ID}',
                    'cancel_url' => full_url('pro/cancel') . '?pid=' . $pid,
                ]);
                DB::update('pro_payments', ['ref' => $s['id']], 'id = ?', [$pid]);
                return (string) $s['url'];
            }
            if ($gateway === 'paypal') {
                $o = self::paypal('POST', 'v2/checkout/orders', [
                    'intent' => 'CAPTURE',
                    'purchase_units' => [['reference_id' => (string) $pid, 'custom_id' => (string) $pid, 'description' => site_name() . ' ' . $plan['name'],
                        'amount' => ['currency_code' => $plan['currency'], 'value' => self::amount((int) $plan['price_cents'], $plan['currency'])]]],
                    'application_context' => ['brand_name' => mb_substr(site_name(), 0, 120), 'user_action' => 'PAY_NOW', 'shipping_preference' => 'NO_SHIPPING',
                        'return_url' => full_url('pro/return/paypal') . '?pid=' . $pid, 'cancel_url' => full_url('pro/cancel') . '?pid=' . $pid],
                ]);
                DB::update('pro_payments', ['ref' => $o['id']], 'id = ?', [$pid]);
                foreach ($o['links'] ?? [] as $l) {
                    if (($l['rel'] ?? '') === 'approve' || ($l['rel'] ?? '') === 'payer-action') {
                        return (string) $l['href'];
                    }
                }
                throw new RuntimeException(t('pro.gw_error'));
            }
            return null; // custom: an admin approves it
        } catch (Throwable $e) {
            DB::update('pro_payments', ['status' => 'failed', 'note' => mb_substr($e->getMessage(), 0, 500)], 'id = ?', [$pid]);
            @file_put_contents(ZL_ROOT . '/data/error.log', date('c') . ' [pro] ' . $gateway . ': ' . $e->getMessage() . "\n", FILE_APPEND | LOCK_EX);
            throw new RuntimeException(t('pro.gw_error'));
        }
    }

    /**
     * Ask Stripe / PayPal whether a pending payment was really paid, and if so give Pro. Used when the member comes back
     * from the payment page, and by the "Check again" button in Admin › Pro. Returns true when the payment is paid.
     */
    public static function verify(int $paymentId): bool
    {
        $p = DB::one('SELECT * FROM {pro_payments} WHERE id = ?', [$paymentId]);
        if (!$p || $p['status'] === 'paid') {
            return (bool) $p;
        }
        if ($p['status'] !== 'pending' || !$p['ref']) {
            return false;
        }
        if ($p['gateway'] === 'stripe') {
            $s = self::stripe('GET', 'checkout/sessions/' . rawurlencode($p['ref']));
            $ok = ($s['payment_status'] ?? '') === 'paid' && (int) ($s['amount_total'] ?? -1) === (int) $p['amount_cents']
                && strtoupper((string) ($s['currency'] ?? '')) === $p['currency'] && (string) ($s['metadata']['payment_id'] ?? '') === (string) $p['id'];
            return $ok && self::markPaid($p);
        }
        if ($p['gateway'] === 'paypal') {
            $o = self::paypal('GET', 'v2/checkout/orders/' . rawurlencode($p['ref']));
            if (($o['status'] ?? '') === 'APPROVED') {
                $o = self::paypal('POST', 'v2/checkout/orders/' . rawurlencode($p['ref']) . '/capture', new stdClass());
            }
            $unit = $o['purchase_units'][0] ?? [];
            $cap = $unit['payments']['captures'][0] ?? [];
            $ok = ($o['status'] ?? '') === 'COMPLETED' && ($cap['status'] ?? '') === 'COMPLETED'
                && ($cap['amount']['value'] ?? '') === self::amount((int) $p['amount_cents'], $p['currency'])
                && ($cap['amount']['currency_code'] ?? '') === $p['currency'] && (string) ($unit['reference_id'] ?? $cap['custom_id'] ?? '') === (string) $p['id'];
            return $ok && self::markPaid($p);
        }
        return false;
    }

    /** Mark a payment paid (once) and add its days to the member's Pro time. */
    public static function markPaid(array $p): bool
    {
        $changed = DB::query("UPDATE {pro_payments} SET status = 'paid', paid_at = ? WHERE id = ? AND status <> 'paid'", [time(), $p['id']])->rowCount();
        if ($changed) {
            self::grant((int) $p['user_id'], (int) $p['days'], (int) $p['plan_id']);
        }
        return true;
    }

    /** Give (or with negative days, take away) Pro time. Paid time adds to time that is still left. */
    public static function grant(int $userId, int $days, ?int $planId = null): void
    {
        $u = Users::find($userId);
        if (!$u) {
            return;
        }
        $from = max(time(), (int) ($u['pro_until'] ?? 0));
        $until = $days > 0 ? $from + $days * 86400 : null;
        DB::update('users', ['pro_until' => $until, 'pro_plan_id' => $days > 0 ? $planId : null], 'id = ?', [$userId]);
    }

    /* ---------- HTTPS calls (curl, no SDK) ---------- */

    private static function stripe(string $method, string $path, array $form = []): array
    {
        return self::http($method, 'https://api.stripe.com/v1/' . $path, ['Authorization: Bearer ' . setting('stripe_secret', '')],
            $method === 'POST' ? http_build_query($form) : null, 'application/x-www-form-urlencoded');
    }

    private static function paypal(string $method, string $path, $json = null): array
    {
        $base = setting('paypal_mode', 'sandbox') === 'live' ? 'https://api-m.paypal.com/' : 'https://api-m.sandbox.paypal.com/';
        $tok = self::http('POST', $base . 'v1/oauth2/token', ['Authorization: Basic ' . base64_encode(setting('paypal_client', '') . ':' . setting('paypal_secret', ''))],
            'grant_type=client_credentials', 'application/x-www-form-urlencoded');
        if (empty($tok['access_token'])) {
            throw new RuntimeException('PayPal login failed');
        }
        return self::http($method, $base . $path, ['Authorization: Bearer ' . $tok['access_token'], 'Prefer: return=representation'],
            $json !== null ? json_encode($json) : null, 'application/json');
    }

    private static function http(string $method, string $url, array $headers, ?string $body, string $type): array
    {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('PHP curl is missing');
        }
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 25, CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => array_merge($headers, ['Accept: application/json'], $body !== null ? ['Content-Type: ' . $type] : [])]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }
        $raw = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        $data = is_string($raw) ? json_decode($raw, true) : null;
        if ($raw === false || $code >= 400 || !is_array($data)) {
            $msg = $data['error']['message'] ?? $data['message'] ?? $data['error_description'] ?? $err ?: ('HTTP ' . $code);
            throw new RuntimeException((string) $msg);
        }
        return $data;
    }
}
