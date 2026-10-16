<?php

namespace App\Domains\Seller\Services;

use App\Domains\Booking\Models\Service;
use App\Domains\Catalog\Models\Product;
use App\Domains\Seller\Enums\Theme;
use App\Domains\Seller\Models\StorePage;
use Illuminate\Support\Facades\Route;

/**
 * Works out what a seller still has to do before their page can go live.
 *
 * One source of truth for two places that would otherwise drift: the checklist on
 * the dashboard, and the gate in PublishStorePage.
 */
class PublishReadiness
{
    /**
     * @return list<array{key: string, label: string, done: bool, blocking: bool, hint: string, url: ?string}>
     */
    public function checks(StorePage $page): array
    {
        return [
            $this->brandCheck($page),
            $this->whatsappCheck($page),
            $this->catalogueCheck($page),
            $this->blocksCheck($page),
            $this->scheduleCheck($page),
        ];
    }

    /**
     * @param  list<array{key: string, label: string, done: bool, blocking: bool, hint: string, url: ?string}>  $checks
     * @return list<array{key: string, label: string, hint: string, url: ?string}>
     */
    public function blockers(array $checks): array
    {
        return array_values(array_map(
            fn (array $check) => array_intersect_key($check, array_flip(['key', 'label', 'hint', 'url'])),
            array_filter($checks, fn (array $check) => $check['blocking'] && ! $check['done']),
        ));
    }

    public function isReady(StorePage $page): bool
    {
        return $this->blockers($this->checks($page)) === [];
    }

    /**
     * @return array{key: string, label: string, done: bool, blocking: bool, hint: string, url: ?string}
     */
    private function brandCheck(StorePage $page): array
    {
        $hasDescription = filled($page->tagline) || filled($page->bio);

        return [
            'key' => 'brand',
            'label' => $page->theme === Theme::Service ? 'Describe what you offer' : 'Describe what you sell',
            'done' => $hasDescription,
            'blocking' => true,
            'hint' => $hasDescription
                ? 'Your business has a description customers can see.'
                : 'Add a short tagline or bio so customers know what this page is for.',
            'url' => route('storefront.edit'),
        ];
    }

    /**
     * @return array{key: string, label: string, done: bool, blocking: bool, hint: string, url: ?string}
     */
    private function whatsappCheck(StorePage $page): array
    {
        $done = filled($page->whatsapp_number);
        $isService = $page->theme === Theme::Service;

        return [
            'key' => 'whatsapp',
            'label' => 'Add your WhatsApp number',
            'done' => $done,
            'blocking' => true,
            'hint' => $done
                ? ($isService
                    ? 'Customers can message you about bookings over WhatsApp.'
                    : 'Buyers can send you an order over WhatsApp.')
                : 'Most Nigerian customers would rather message you than fill in a form. Add your number.',
            'url' => route('storefront.edit'),
        ];
    }

    /**
     * A page with nothing to sell is worse than no page at all, so a theme needs
     * at least one live item before it can be published.
     *
     * @return array{key: string, label: string, done: bool, blocking: bool, hint: string, url: ?string}
     */
    private function catalogueCheck(StorePage $page): array
    {
        $isService = $page->theme === Theme::Service;

        $count = $isService
            ? Service::where('store_page_id', $page->getKey())->where('is_active', true)->count()
            : Product::where('store_page_id', $page->getKey())->where('status', 'active')->count();

        $offering = strtolower($page->theme->offeringNoun());

        return [
            'key' => 'catalogue',
            'label' => 'Add a '.$offering,
            'done' => $count > 0,
            'blocking' => true,
            'hint' => $count > 0
                ? "You have {$count} ".($isService ? 'active' : 'live').' '.strtolower($page->theme->offeringNoun($count !== 1)).'.'
                : 'Add at least one '.$offering.($isService
                    ? ' so visitors can book it.'
                    : ' so buyers have something to look at.'),
            'url' => $isService
                ? (Route::has('services.index') ? route('services.index') : null)
                : route('products.index'),
        ];
    }

    /**
     * A booking page with no opening hours renders a widget that can never return a
     * slot. Better caught here than by a customer wondering why nothing is bookable.
     *
     * @return array{key: string, label: string, done: bool, blocking: bool, hint: string, url: ?string}
     */
    private function scheduleCheck(StorePage $page): array
    {
        // Only the service theme has anything to schedule.
        if ($page->theme !== Theme::Service) {
            return [
                'key' => 'schedule',
                'label' => 'Opening hours',
                'done' => true,
                'blocking' => false,
                'hint' => '',
                'url' => null,
            ];
        }

        $days = $page->workingHours()->whereNull('staff_member_id')->distinct()->count('weekday');

        return [
            'key' => 'schedule',
            'label' => 'Set your opening hours',
            'done' => $days > 0,
            'blocking' => true,
            'hint' => $days > 0
                ? "You are open on {$days} ".($days === 1 ? 'day' : 'days').' a week.'
                : 'Without opening hours customers cannot book anything, however good your services are.',
            'url' => Route::has('availability.index') ? route('availability.index', ['staff' => null]) : null,
        ];
    }

    /**
     * @return array{key: string, label: string, done: bool, blocking: bool, hint: string, url: ?string}
     */
    private function blocksCheck(StorePage $page): array
    {
        // Counted across every page, not just home: a seller who only filled in
        // their contact details has still written a real page.
        //
        // The parent is attached to each block rather than lazy-loaded: isEmpty()
        // reads the block's schema, and the schema is keyed by the theme on the
        // page this method was handed — so the row is already in memory.
        $written = $page->blocks()->get()
            ->each->setRelation('storePage', $page)
            ->reject->isEmpty()
            ->count();

        return [
            'key' => 'blocks',
            'label' => 'Write something on your page',
            'done' => $written > 0,
            'blocking' => true,
            'hint' => $written > 0
                ? "{$written} blocks filled in."
                : 'Add a headline or two, or your page will be blank.',
            'url' => route('storefront.edit'),
        ];
    }
}
