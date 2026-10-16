<?php

namespace Database\Seeders;

use App\Domains\Admin\Models\AdminAuditLog;
use App\Domains\Admin\Models\BadgeApplication;
use App\Domains\Admin\Models\BadgeStatus;
use App\Domains\Billing\Actions\SubscriptionManager;
use App\Domains\Billing\Models\FeatureFlag;
use App\Domains\Billing\Models\Plan;
use App\Domains\Billing\Models\Subscription;
use App\Domains\Booking\Enums\BillingUnit;
use App\Domains\Booking\Enums\BookingStatus;
use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Models\BookingStatusHistory;
use App\Domains\Booking\Models\Service;
use App\Domains\Booking\Models\StaffMember;
use App\Domains\Booking\Models\TimeOff;
use App\Domains\Booking\Models\WorkingHour;
use App\Domains\Catalog\Models\Category;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\Review;
use App\Domains\Customers\Actions\UpsertCustomerFromBooking;
use App\Domains\Customers\Actions\UpsertCustomerFromOrder;
use App\Domains\Customers\Models\Customer;
use App\Domains\Customers\Models\CustomerNote;
use App\Domains\Orders\Enums\AbandonedCartStatus;
use App\Domains\Orders\Enums\OrderSource;
use App\Domains\Orders\Enums\OrderStatus;
use App\Domains\Orders\Models\AbandonedCart;
use App\Domains\Orders\Models\Order;
use App\Domains\Orders\Models\OrderItem;
use App\Domains\Orders\Models\OrderStatusHistory;
use App\Domains\Payments\Enums\PayoutStatus;
use App\Domains\Payments\Enums\TransactionStatus;
use App\Domains\Payments\Models\Payout;
use App\Domains\Payments\Models\Transaction;
use App\Domains\Payments\Models\Wallet;
use App\Domains\Payments\Models\WalletLedger;
use App\Domains\Seller\Enums\PriceTier;
use App\Domains\Seller\Enums\ReviewStatus;
use App\Domains\Seller\Models\PageBlock;
use App\Domains\Seller\Models\Seller;
use App\Domains\Seller\Models\StorePage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Two realistic sellers, one per theme, with enough history that every screen has
 * something to show. Password for all accounts is "password".
 *
 * Safe to re-run: it truncates the demo tables first rather than duplicating.
 */
class DemoSellerSeeder extends Seeder
{
    private const PASSWORD = 'password';

    private const ADMIN_EMAIL = 'admin@mypage.test';

    public function run(): void
    {
        $this->wipe();

        $store = $this->storeSeller();
        $service = $this->serviceSeller();

        $this->trustAndFlags($store, $service);
        $this->money($store, $service);
        $this->discovery($store, $service);
        $this->reviews($store, $service);

        $this->command?->newLine();
        $this->command?->info('Demo sellers ready. Password for all accounts: '.self::PASSWORD);
        $this->command?->table(
            ['Seller', 'Email', 'Theme', 'Storefront', 'Role'],
            [
                [$store['seller']->name, $store['seller']->email, 'store', '/'.$store['page']->slug, 'seller'],
                [$service['seller']->name, $service['seller']->email, 'service', '/'.$service['page']->slug, 'seller'],
                ['Ops Admin', self::ADMIN_EMAIL, '-', '/admin', 'admin'],
            ]
        );
    }

    /**
     * Real, approved reviews on both demo businesses and a few of their listings.
     *
     * Seeded rather than left empty because the reviews UI has three states and
     * a fresh seed shows only one of them: a seller with no reviews at all looks
     * identical to a reviews feature that does not work.
     *
     * Two are left pending on purpose, so the moderation queue opens with
     * something in it. A demo where everything is already published never
     * exercises the only part of this feature that is actually load-bearing.
     *
     * The aggregates are recomputed through the model rather than written by
     * hand, so the demo cannot drift from what recalculateRating() produces.
     */
    private function reviews(array $store, array $service): void
    {
        $storeQuotes = [
            ['Ngozi James', 'Lekki', 5, 'The Ankara is exactly the colour in the photo and it took three fittings without a single complaint.', true],
            ['Chidi Okeke', 'Ikeja', 5, 'Ordered on a Tuesday, worn on a Saturday. My tailor asked where I had it made.', true],
            ['Blessing Yusuf', 'Surulere', 4, 'Beautiful cloth. Delivery was a day late but I would order again tomorrow.', false],
            ['Emeka Obi', null, 5, 'Thirty orders in and not one has been the wrong size. That is the whole review.', false],
            ['Fatima Bello', 'Yaba', 4, 'Good prices and they answer the phone. What more do you want?', false],
            ['Tunde Akin', 'Lekki', 2, 'The fabric was fine but nobody replied for a week about the fitting.', false],
        ];

        $serviceQuotes = [
            ['Ada Okonkwo', 'Lekki', 5, "The braids survived my sister's wedding and a hospital visit. Three weeks and still neat.", true],
            ['Zainab Yusuf', 'Surulere', 5, 'Booked at short notice and she still had time for a proper consultation. Genuinely skilled.', false],
            ['Maryam Bello', 'Ikeja', 4, 'Lovely finish. The wait is real at weekends, so book ahead.', true],
            ['Grace Eze', null, 5, 'I have been coming here for two years. Never had a bad appointment.', false],
            ['Bisi Ade', 'Yaba', 3, 'Good braids, but the room was colder than I expected in December.', false],
        ];

        foreach ([[$store, $storeQuotes], [$service, $serviceQuotes]] as [$account, $quotes]) {
            $page = $account['page'];

            foreach ($quotes as $index => [$name, $location, $rating, $body, $featured]) {
                $review = Review::withoutGlobalScopes()->create([
                    'seller_id' => $page->seller_id,
                    'store_page_id' => $page->id,
                    'reviewable_type' => $page->getMorphClass(),
                    'reviewable_id' => $page->id,
                    'author_name' => $name,
                    'author_location' => $location,
                    'rating' => $rating,
                    'body' => $body,
                    'is_featured' => $featured,
                    'status' => ReviewStatus::Approved,
                    'published_at' => now()->subDays(($index + 1) * 6),
                ]);
            }

            // The last one on each page goes back to pending, so the dashboard
            // inbox has something real in it.
            Review::withoutGlobalScopes()
                ->where('store_page_id', $page->id)
                ->where('reviewable_id', $page->id)
                ->orderByDesc('id')
                ->limit(1)
                ->update([
                    'status' => ReviewStatus::Pending,
                    'published_at' => null,
                ]);

            $page->recalculateRating();
        }

        // A couple of product-level reviews, so the product card's star summary
        // and the product detail list are not empty on a fresh seed.
        foreach ($store['page']->products()->active()->limit(3)->get() as $index => $product) {
            $rating = [5, 4, 5][$index];

            $product->reviews()->create([
                'seller_id' => $store['page']->seller_id,
                'store_page_id' => $store['page']->id,
                'author_name' => ['Ngozi James', 'Emeka Obi', 'Blessing Yusuf'][$index],
                'author_location' => ['Lekki', null, 'Surulere'][$index],
                'rating' => $rating,
                'body' => [
                    'Bought two lengths and both were exactly 6 yards as listed.',
                    'Softens beautifully after the first wash. Would buy again.',
                    'The colour is warmer in person, which I actually prefer.',
                ][$index],
                'status' => ReviewStatus::Approved,
                'published_at' => now()->subDays($index + 2),
            ]);

            $product->recalculateRating();
        }

        $this->command?->newLine();
        $this->command?->info('Reviews seeded, including one waiting in each seller inbox.');
    }

    /**
     * Both demo businesses put in the public discovery hub.
     *
     * Without this the hub is empty on a fresh `migrate:fresh --seed`, and an
     * empty discovery page is indistinguishable from a broken one — you cannot
     * tell whether the query is wrong or there is simply nothing to show.
     *
     * One of each type, at different tiers and with different ratings, so the
     * hub's filters, its ordering and its marquee split all have something to
     * act on.
     */
    private function discovery(array $store, array $service): void
    {
        $store['page']->forceFill([
            'is_directory_opted_in' => true,
            'price_tier' => PriceTier::Standard,
            'rating' => 4.50,
            'review_count' => 38,
            'view_count' => 412,
            'is_featured' => true,
        ])->save();

        $service['page']->forceFill([
            'is_directory_opted_in' => true,
            'price_tier' => PriceTier::Premium,
            'rating' => 4.80,
            'review_count' => 96,
            'view_count' => 1_204,
            'is_featured' => false,
        ])->save();

        $this->command?->newLine();
        $this->command?->info('Both demo businesses are listed at /discover (behind FEATURE_DISCOVERY).');
    }

    /**
     * Transactions and payouts, so the admin money screens have something in
     * them. Written straight to the models rather than driven through the
     * gateways: this is display data, and a seeder that depends on Paystack
     * being reachable is a seeder that breaks the day the API rate limits us.
     */
    private function money(array $store, array $service): void
    {
        $commissionBps = (int) config('mypage.commission_bps', 150);

        foreach ([$store, $service] as $index => $account) {
            $seller = $account['seller'];
            $page = $account['page'];

            $orders = Order::withoutGlobalScopes()
                ->where('store_page_id', $page->id)
                ->whereIn('status', [OrderStatus::Paid, OrderStatus::Shipped, OrderStatus::Delivered])
                ->get();

            foreach ($orders as $n => $order) {
                $commission = (int) round($order->total_kobo * ($commissionBps / 10000));
                $paidAt = $order->created_at->copy()->addMinutes(3);

                Transaction::withoutGlobalScopes()->create([
                    'seller_id' => $seller->id,
                    'payable_type' => $order->getMorphClass(),
                    'payable_id' => $order->id,
                    'reference' => $order->reference,
                    'gateway' => $index === 0 ? 'paystack' : 'paystack',
                    'amount_kobo' => $order->total_kobo,
                    'fee_kobo' => (int) round($order->total_kobo * 0.015),
                    'commission_kobo' => $commission,
                    // One deliberate failure so the status filter has something
                    // in it that is not a success.
                    'status' => $n === 1 && $index === 0 ? TransactionStatus::Failed : TransactionStatus::Paid,
                    'paid_at' => $paidAt,
                    'gateway_payload' => ['id' => 3_000_000_000 + $order->id],
                ]);

                Transaction::withoutGlobalScopes()->create([
                    'seller_id' => $seller->id,
                    'payable_type' => $order->getMorphClass(),
                    'payable_id' => $order->id,
                    'reference' => $order->reference.'R',
                    'gateway' => 'paystack',
                    'amount_kobo' => $order->total_kobo,
                    'fee_kobo' => 0,
                    'commission_kobo' => $commission,
                    'status' => TransactionStatus::Pending,
                ]);
            }
        }

        // One failed payout, so the admin payout queue is not empty.
        Payout::withoutGlobalScopes()->create([
            'seller_id' => $service['seller']->id,
            'reference' => 'PO'.Str::upper(Str::random(10)),
            'amount_kobo' => 120000,
            'status' => PayoutStatus::Failed,
            'bank_code' => '058',
            'account_number' => '2034888888',
            'account_name' => 'Kemi Adeyemi',
            'gateway' => 'paystack',
            'failure_reason' => 'Paystack rejected the transfer: account is restricted.',
            'processed_at' => now()->subDays(2),
        ]);

        Payout::withoutGlobalScopes()->create([
            'seller_id' => $store['seller']->id,
            'reference' => 'PO'.Str::upper(Str::random(10)),
            'amount_kobo' => 850000,
            'status' => PayoutStatus::Paid,
            'bank_code' => '007',
            'account_number' => '0123456789',
            'account_name' => 'Ada Textiles Limited',
            'gateway' => 'paystack',
            'gateway_reference' => 'TRF'.Str::upper(Str::random(8)),
            'processed_at' => now()->subDays(3),
        ]);

        // And a healthy balance for the store seller, so /payouts opens with
        // something to withdraw rather than an empty wallet.
        Wallet::forSeller($store['seller']->id)->increment('balance_kobo', 1_850_000);

        $store['seller']->forceFill([
            'payout_bank_code' => '007',
            'payout_account_number' => '0123456789',
            'payout_account_name' => 'Ada Textiles Limited',
        ])->save();

        $service['seller']->forceFill([
            'payout_bank_code' => '058',
            'payout_account_number' => '2034888888',
            'payout_account_name' => 'Kemi Adeyemi',
        ])->save();
    }

    /**
     * The verified badge in its three states, plus one frozen escrow, so the
     * admin queue has something to actually decide on.
     */
    private function trustAndFlags(array $store, array $service): void
    {
        $admin = Seller::factory()->admin()->create([
            'name' => 'Ops Admin',
            'email' => self::ADMIN_EMAIL,
            'password' => Hash::make(self::PASSWORD),
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        // Ada earned the tick. The page is stamped too, because that column is
        // what the directory sort and the structured data read.
        $store['page']->forceFill(['verified_at' => now()->subDays(10)])->save();

        BadgeApplication::create([
            'seller_id' => $store['seller']->getKey(),
            'store_page_id' => $store['page']->getKey(),
            'status' => BadgeStatus::Approved,
            'business_name' => 'Ada Textiles Limited',
            'document_type' => 'cac',
            'document_path' => 'badges/demo/ada-cac.pdf',
            'submitted_at' => now()->subDays(12),
            'reviewed_by_seller_id' => $admin->getKey(),
            'reviewed_at' => now()->subDays(10),
        ]);

        // One waiting, so the review queue is not empty on a fresh seed.
        BadgeApplication::create([
            'seller_id' => $service['seller']->getKey(),
            'store_page_id' => $service['page']->getKey(),
            'status' => BadgeStatus::Pending,
            'business_name' => 'Kemi Braids & Beauty Ltd',
            'document_type' => 'national_id',
            'document_path' => 'badges/demo/kemi-id.jpg',
            'note' => 'Trading name is Kemi Braids; the ID is under my legal name.',
            'submitted_at' => now()->subDay(),
        ]);

        AdminAuditLog::create([
            'admin_seller_id' => $admin->getKey(),
            'action' => 'badge.approved',
            'subject_type' => (new BadgeApplication)->getMorphClass(),
            'subject_id' => BadgeApplication::where('seller_id', $store['seller']->getKey())->value('id'),
            'note' => 'CAC certificate matches the trading name.',
        ]);
    }

    private function wipe(): void
    {
        // Ordered by dependency: the foreign keys do the work, but being explicit
        // keeps this readable and lets it run on a database with no constraints.
        foreach ([
            WalletLedger::class,
            Payout::class,
            Transaction::class,
            AbandonedCart::class,
            OrderStatusHistory::class,
            AdminAuditLog::class,
            BadgeApplication::class,
            OrderItem::class,
            Order::class,
            BookingStatusHistory::class,
            Booking::class,
            WorkingHour::class,
            TimeOff::class,
            Service::class,
            StaffMember::class,
            CustomerNote::class,
            Customer::class,
            Product::class,
            Category::class,
            PageBlock::class,
            StorePage::class,
            Subscription::class,
            FeatureFlag::class,
            Seller::class,
        ] as $model) {
            if (class_exists($model)) {
                $model::withoutGlobalScopes()->forceDelete();
            }
        }
    }

    private function storeSeller(): array
    {
        $seller = $this->seller('Ada Textiles', 'ada@mypage.test', '+2348001112223');
        $page = $this->page($seller, 'ada-textiles', 'store', [
            'name' => 'Ada Textiles',
            'tagline' => 'Hand-cut Ankara for weddings and aso ebi',
            'bio' => 'We have been cutting and sewing Ankara in Lagos since 2014. Every piece is made to measure, and we deliver across Lagos State within three days.',
            'whatsapp_number' => '+2348031234567',
            'city' => 'Lekki',
            'state' => 'Lagos',
            'address' => '22b Oduduwa Crescent, Lekki Phase 1, Lagos',
            'public_email' => 'ada@example.com',
            'brand_color' => '#B45309',
            'settings' => ['timezone' => 'Africa/Lagos'],
            'social_links' => [
                'instagram' => 'https://www.instagram.com/adatextiles',
                'facebook' => 'https://www.facebook.com/adatextiles',
                'tiktok' => 'https://www.tiktok.com/@adatextiles',
            ],
        ]);

        $categories = collect([
            ['Ankara', 'Hand-cut and tailored Ankara for ceremonies'],
            ['Lace', 'Ready-to-wear lace and Aso ebi'],
            ['Silk', 'Eveningwear and senator styles'],
        ])->mapWithKeys(function (array $row, int $index) use ($seller, $page) {
            $category = Category::factory()->create([
                'seller_id' => $seller->id,
                'store_page_id' => $page->id,
                'name' => $row[0],
                'slug' => Str::slug($row[0]).'-'.$index,
                'position' => $index,
            ]);

            return [$row[0] => $category];
        });

        $catalogue = [
            ['Ankara Gown', 'Ankara', 1850000, 12, true],
            ['Two Piece Set', 'Ankara', 950000, 20, true],
            ['Aso Ebi Gown', 'Lace', 2400000, 6, true],
            ['Buba and Trouser', 'Lace', 1650000, 9, false],
            ['Silk Dinner Gown', 'Silk', 3200000, 4, true],
            ['Senator Style', 'Silk', 2800000, 5, false],
            ['Simple Blouse', 'Ankara', 450000, 25, false],
            ['Wedding Gown', 'Lace', 4500000, 2, true],
        ];

        foreach ($catalogue as [$name, $categoryName, $priceKobo, $stock, $featured]) {
            Product::factory()->create([
                'seller_id' => $seller->id,
                'store_page_id' => $page->id,
                'category_id' => $categories[$categoryName]->id,
                'name' => $name,
                'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)),
                'description' => "{$name} made to measure in our Lekki studio. Bring your measurements or send them on WhatsApp and we will confirm within a day.",
                'price_kobo' => $priceKobo,
                'sku' => 'ADA-'.strtoupper(Str::random(6)),
                'track_stock' => true,
                'stock_qty' => $stock,
                'status' => 'active',
                'is_featured' => $featured,
            ]);
        }

        $this->orderHistory($seller, $page);

        $this->subscribe($seller, 'growth');

        return ['seller' => $seller, 'page' => $page];
    }

    private function serviceSeller(): array
    {
        $seller = $this->seller('Kemi Braids', 'kemi@mypage.test', '+2348004445556');
        $page = $this->page($seller, 'kemi-braids', 'service', [
            'name' => 'Kemi Braids',
            'tagline' => 'Braids, locs and treatment in Surulere',
            'bio' => 'A chair at Kemi Braids is a full consultation, not a rush. We work Monday to Saturday and finish with a style you can maintain at home.',
            'whatsapp_number' => '+2348098765432',
            'city' => 'Surulere',
            'state' => 'Lagos',
            'address' => '14B Adeniran Ogunsanya Street, Surulere, Lagos',
            'public_email' => 'kemi@example.com',
            'brand_color' => '#7C3AED',
            'settings' => ['timezone' => 'Africa/Lagos', 'min_notice_minutes' => 120, 'cancellation_window_hours' => 24],
            'social_links' => [
                'instagram' => 'https://www.instagram.com/kemibraids',
                'youtube' => 'https://www.youtube.com/@kemibraids',
            ],
        ]);

        // Open every day except Sunday, so the availability screen has something
        // real on it and the booking widget returns slots.
        foreach ([1 => '09:00', 2 => '09:00', 3 => '09:00', 4 => '10:00', 5 => '09:00', 6 => '08:00'] as $weekday => $start) {
            WorkingHour::create([
                'store_page_id' => $page->id,
                'weekday' => $weekday,
                'start_time' => $start,
                'end_time' => $weekday === 6 ? '16:00' : '18:00',
            ]);
        }

        $staff = collect([
            ['Kemi Adeyemi', 'Founder and lead stylist, 14 years on the chair.'],
            ['Tolu Bakare', 'Specialises in knotless braids and protective styles.'],
            ['Ngozi Eze', 'Colour, retouch and treatment specialist.'],
        ])->map(fn (array $row) => StaffMember::factory()->create([
            'seller_id' => $seller->id,
            'store_page_id' => $page->id,
            'name' => $row[0],
            'bio' => $row[1],
            'is_active' => true,
        ]));

        $services = collect([
            // name, description, minutes, kobo, deposit type, deposit value,
            // billing unit, turnaround, deliverables, featured
            ['Knotless Braids', 'Shoulder length, comes with a treatment and a style guide.', 180, 4500000, 'fixed', 1000000,
                BillingUnit::PerProject, '2 weeks', ['Consultation and length check', 'Braid and install', 'Deep treatment on the day', 'Wash and style guide'], true],
            ['Box Braids', 'Full head, small or medium boxes. Roughly five hours.', 240, 5500000, 'fixed', 1500000,
                BillingUnit::PerProject, '2 weeks', ['Full head of boxes', 'Edge styling', 'Treatment on the day', 'Home care routine'], false],
            ['Locs Retouch', 'Retouch, wash and style. Price depends on length.', 150, 3000000, 'percent', 30,
                BillingUnit::PerProject, '3 hours', ['Retouch and wash', 'Styling and shaping', 'Product for aftercare'], false],
            ['Straw Braids', 'A full day style for weddings and parties.', 300, 6500000, 'percent', 40,
                BillingUnit::PerProject, 'Same day', ['Full day appointment', 'Pinning and finish', 'Accessories to match'], false],
            // The one priced by the hour, so the directory's billing-unit filter
            // and its per-hour suffix both have something real to act on.
            ['Treatment and Blow Dry', 'Deep treatment, wash and finish.', 90, 1200000, 'none', 0,
                BillingUnit::PerHour, 'While you wait', ['Wash and treatment', 'Blow dry and finish'], false],
        ])->map(fn (array $row) => Service::factory()->create([
            'seller_id' => $seller->id,
            'store_page_id' => $page->id,
            'name' => $row[0],
            'slug' => Str::slug($row[0]).'-'.Str::lower(Str::random(4)),
            'description' => $row[1],
            'duration_min' => $row[2],
            'buffer_min' => 30,
            'price_kobo' => $row[3],
            'deposit_type' => $row[4],
            'deposit_value' => $row[5],
            'billing_unit' => $row[6],
            'turnaround_label' => $row[7],
            'deliverables' => $row[8],
            'is_featured' => $row[9],
            'is_active' => true,
            'view_count' => random_int(20, 400),
        ]));

        $services->each(fn (Service $service) => $service->staff()->sync($staff->random(2)->pluck('id')));

        $this->bookingHistory($seller, $page, $services, $staff);

        $this->subscribe($seller, 'pro');

        return ['seller' => $seller, 'page' => $page];
    }

    private function orderHistory(Seller $seller, StorePage $page): void
    {
        $buyers = [
            ['Ngozi James', 'ngozi@example.com', '08031234567'],
            ['Chidi Okeke', 'chidi@example.com', '08097654321'],
            ['Blessing Yusuf', 'blessing@example.com', '08123456789'],
            ['Emeka Obi', 'emeka@example.com', '07066554433'],
        ];

        $products = Product::where('store_page_id', $page->id)->get();

        foreach ($buyers as $index => [$name, $email, $phone]) {
            $placedAt = now()->subDays($index * 6 + 1)->setTime(11 + $index, 30);

            $order = Order::withoutGlobalScopes()->create([
                'seller_id' => $seller->id,
                'store_page_id' => $page->id,
                'reference' => 'MP'.now()->format('ymd').Str::upper(Str::random(4)),
                'source' => $index % 2 === 0 ? OrderSource::Platform : OrderSource::Whatsapp,
                'status' => [OrderStatus::Delivered, OrderStatus::Shipped, OrderStatus::Paid, OrderStatus::Pending][$index] ?? OrderStatus::Paid,
                'total_kobo' => 0,
                'customer_name' => $name,
                'customer_email' => $email,
                'customer_phone' => $phone,
                'customer_note' => $index === 0 ? 'Delivering to Lekki Phase 1, please call on arrival.' : null,
            ]);

            $order->forceFill(['created_at' => $placedAt, 'updated_at' => $placedAt])->save();

            $total = 0;

            foreach ($products->random($index + 1) as $product) {
                $quantity = fake()->numberBetween(1, 2);

                $order->items()->create([
                    'product_id' => $product->id,
                    'name' => $product->name,
                    'sku' => $product->sku,
                    'unit_kobo' => $product->price_kobo,
                    'quantity' => $quantity,
                    'line_kobo' => $product->price_kobo * $quantity,
                ]);

                $total += $product->price_kobo * $quantity;
            }

            $order->update(['total_kobo' => $total]);

            // The customer book is normally built by the order itself; seeding it
            // the same way keeps the demo data consistent with a real flow.
            $customer = app(UpsertCustomerFromOrder::class)->handle($order);

            // Lifetime value normally accrues through OrderStatusChanged when a
            // payment lands. Seeded orders skip that, so mirror the rule here or
            // the customer list shows nobody worth anything.
            $earned = $order->status->countsTowardsLifetimeValue();

            $customer?->update([
                'opted_in' => $index % 2 === 1,
                'lifetime_value_kobo' => $earned ? $total : 0,
            ]);

            $order->history()->create([
                'from_status' => null,
                'to_status' => $order->status,
                'note' => 'Demo order',
            ]);
        }

        // One cart a buyer walked away from, so the abandoned screen has content.
        $product = $products->first();

        AbandonedCart::withoutGlobalScopes()->create([
            'seller_id' => $seller->id,
            'store_page_id' => $page->id,
            'reference' => 'AC'.now()->format('ymd').Str::upper(Str::random(4)),
            'email' => 'tired-buyer@example.com',
            'phone' => '+2348090001122',
            'cart' => [[
                'name' => $product->name,
                'quantity' => 2,
                'line_kobo' => $product->price_kobo * 2,
            ]],
            'total_kobo' => $product->price_kobo * 2,
            'status' => AbandonedCartStatus::Open,
        ]);
    }

    private function bookingHistory(Seller $seller, StorePage $page, $services, $staff): void
    {
        $guests = [
            ['Temi Akin', 'temi@example.com', '+2348032223344'],
            ['Ada Okoro', 'ada.o@example.com', '+2348098887766'],
            ['Zainab Lawal', 'zainab@example.com', '+2348076655443'],
            ['Ifeoma Uche', 'ifeoma@example.com', '+2348054433221'],
        ];

        foreach ($guests as $index => [$name, $email, $phone]) {
            $service = $services[$index % $services->count()];
            $deposit = $service->depositKobo();

            // Spread across past, today and the next fortnight.
            $startsAt = match ($index) {
                0 => Carbon::now('Africa/Lagos')->subDays(3)->setTime(11, 0),
                1 => Carbon::now('Africa/Lagos')->addHours(5),
                default => Carbon::now('Africa/Lagos')->addDays($index + 1)->setTime(10 + $index, 0),
            };

            $status = match ($index) {
                0 => BookingStatus::Completed,
                1 => BookingStatus::Confirmed,
                default => BookingStatus::Confirmed,
            };

            $booking = Booking::withoutGlobalScopes()->create([
                'seller_id' => $seller->id,
                'store_page_id' => $page->id,
                'service_id' => $service->id,
                'staff_member_id' => $staff->random()->id,
                'reference' => 'BK'.Str::upper(Str::random(8)),
                'starts_at' => $startsAt->copy()->utc(),
                'ends_at' => $startsAt->copy()->addMinutes($service->duration_min)->utc(),
                'status' => $status,
                'price_kobo' => $service->price_kobo,
                'deposit_kobo' => $deposit,
                'customer_name' => $name,
                'customer_email' => $email,
                'customer_phone' => $phone,
                'notes' => $index === 0 ? 'Coming with my sister, same colour as last time please.' : null,
                'source' => 'web',
            ]);

            $booking->history()->create([
                'from_status' => null,
                'to_status' => $status,
                'note' => 'Demo booking',
            ]);

            // Same as orders: the booking book is built through the action a
            // real booking runs, then the money column is mirrored, because a
            // seeded booking is born in its final status and so never fires a
            // status change.
            $customer = app(UpsertCustomerFromBooking::class)->handle($booking);

            $customer?->update([
                'opted_in' => $index % 2 === 0,
                'lifetime_value_kobo' => $status->countsTowardsLifetimeValue() ? $deposit : 0,
            ]);
        }
    }

    private function seller(string $name, string $email, string $phone): Seller
    {
        return Seller::factory()->create([
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'password' => Hash::make(self::PASSWORD),
            'status' => 'active',
            // Verified up front so the dashboard opens without a detour.
            'email_verified_at' => now(),
        ]);
    }

    private function page(Seller $seller, string $slug, string $theme, array $attributes): StorePage
    {
        $page = StorePage::factory()->for($seller)->create(array_merge([
            'slug' => $slug,
            'theme' => $theme,
            'status' => 'published',
            'published_at' => now()->subDays(20),
            'settings' => ['timezone' => 'Africa/Lagos'],
        ], $attributes));

        // Only the blocks a seller has actually written. Empty ones are not
        // seeded at all, which is also how they behave in production: the theme
        // skips anything with no content.
        foreach ($this->seededBlocks($theme) as $pageKey => $blocks) {
            foreach ($blocks as $key => $content) {
                $page->blocks()->create([
                    'page' => $pageKey,
                    'key' => $key,
                    'content' => $content,
                ]);
            }
        }

        return $page;
    }

    /**
     * Realistic storefront copy, so the demo shows a shop that looks set up
     * rather than a grid of default headings.
     *
     * @return array<string, array<string, array<string, mixed>>>
     */
    private function seededBlocks(string $theme): array
    {
        if ($theme === 'service') {
            return [
                'home' => [
                    'hero' => [
                        'headline' => 'Braids that survive your exam, your wedding and your aunty',
                        'subtext' => 'Knotless, box and protective styles. Lagos, by appointment.',
                        'button_label' => 'Book now',
                    ],
                    'services' => ['heading' => 'What we do'],
                    'team' => ['heading' => 'Who you will be sitting with'],
                    'reviews' => [
                        'heading' => 'What clients say',
                        'items' => [
                            ['quote' => 'Wore these to my engagement and got stopped in the street. Lasted three weeks without a re-braid.', 'author' => 'Adaeze O.'],
                            ['quote' => 'The only person I trust with my edges. Never once pulled my hair out.', 'author' => 'Funmilayo'],
                        ],
                    ],
                    'about' => [
                        'heading' => 'About the studio',
                        'body' => 'Kemi has been doing braids out of a backroom in Surulere for fourteen years, and has never once been late to an appointment.',
                    ],
                ],
                'contact' => [
                    'details' => [
                        'intro' => 'Come to the studio or we come to you for large bookings.',
                        'hours' => 'Tuesday to Saturday, 10am - 7pm',
                        'address' => '14B Adeniran Ogunsanya Street, Surulere, Lagos',
                    ],
                ],
            ];
        }

        return [
            'home' => [
                'hero' => [
                    'headline' => 'Ankara and lace, cut and stitched to measure',
                    'subtext' => 'Made in our Surulere studio, ready in seven days.',
                    'button_label' => 'Shop now',
                ],
                'featured' => ['heading' => 'New arrivals'],
                'categories' => ['heading' => 'Shop by category'],
                'about' => [
                    'heading' => 'About the studio',
                    'body' => 'Ada started with one machine and a market stall in 2019. Everything is cut, sewn and pressed in the same room, by the same four people.',
                ],
                'reviews' => [
                    'heading' => 'What buyers say',
                    'items' => [
                        ['quote' => 'Fitting was perfect and the lace did not scratch at all. Third order.', 'author' => 'Ngozi, Lagos'],
                        ['quote' => 'Delivered to Abuja in two days. Worth it.', 'author' => 'Ibrahim'],
                    ],
                ],
            ],
            'about' => [
                'story' => [
                    'heading' => 'How we started',
                    'body' => 'Ada Textiles began as a market stall and is now a studio with four machinists. We still cut every piece ourselves.',
                ],
            ],
            'contact' => [
                'details' => [
                    'intro' => 'Message us on WhatsApp for fittings and delivery.',
                    'hours' => "Monday to Friday, 9am - 6pm\nSaturday, 10am - 4pm",
                    'address' => '22b Oduduwa Crescent, Egbeda, Lagos',
                ],
            ],
        ];
    }

    private function subscribe(Seller $seller, string $planSlug): void
    {
        $plan = Plan::where('slug', $planSlug)->first();

        if (! $plan) {
            return;
        }

        app(SubscriptionManager::class)->start($seller, $plan, 'gateway', 'SUB_DEMO_'.Str::upper(Str::random(6)));
    }
}
