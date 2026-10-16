<?php

namespace App\Domains\Directory\Http\Controllers;

use App\Domains\Directory\Http\Requests\DiscoveryRequest;
use App\Domains\Directory\Services\DiscoveryQuery;
use App\Domains\Seller\Enums\Theme;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

/**
 * The central discovery hub: every business on the platform that has asked to be
 * found, split by whether it sells products or takes bookings.
 *
 * One route serves both shapes of response. A first request renders the whole
 * page; a request that identifies itself as a live filter renders only the
 * results fragment and lets Alpine swap it in. Keeping them on one URL is
 * deliberate — a second endpoint for "the AJAX version" means the pagination
 * links, the browser Back button and anything shared from the address bar all
 * have to be taught about both.
 */
class DiscoveryController extends Controller
{
    /**
     * The header a first-party request carries. X-Requested-With is the convention
     * jQuery established and every browser still sends; X-Partial is for anything
     * using fetch, where the caller says which fragment it wants in as many words.
     */
    private const PARTIAL_HEADER = 'X-Partial';

    /**
     * No Illuminate\Http\Request parameter here, on purpose.
     *
     * DiscoveryRequest *is* a request. Asking for a second one would read as though
     * two different requests were in play, and Laravel's dependency resolver sees
     * the FormRequest already sitting in the parameter list, decides the Request is
     * satisfied, and drops it — so the action is called with one argument short.
     * The filter object answers everything the plain request would have.
     */
    public function index(DiscoveryRequest $filters, DiscoveryQuery $query): View
    {
        $theme = $filters->theme();

        // Filters narrow the results grid; without them the hub shows the marquee.
        // Both states are the same page at different widths, so the swap is a
        // fragment replace rather than a navigation.
        $viewData = [
            'filters' => $filters,
            'theme' => $theme,
            'cities' => $query->cities($theme),
            'tiers' => $query->tiersInUse($theme),
            'typeCounts' => $query->typeCounts(),
            'totalDiscoverable' => $query->totalDiscoverable(),
        ];

        if ($filters->hasFilters()) {
            $viewData['results'] = $query->paginate($filters);
            $viewData['total'] = $query->total($filters);
        } else {
            $viewData['columns'] = $query->splitIntoColumns($query->marqueeRows()->all());
            $viewData['featured'] = $query->topRated();
        }

        if ($this->wantsFragment($filters)) {
            return view($filters->hasFilters()
                ? 'discovery.partials.results'
                : 'discovery.partials.marquee', $viewData);
        }

        return view('discovery.index', $viewData);
    }

    /**
     * Whether the caller asked for a fragment rather than a page.
     *
     * Only honoured for XMLHttpRequest or the explicit header. Treating any
     * unknown client as wanting a fragment would mean a crawler, a preview bot or
     * curl received a bare grid with no heading and no way back, and the hub's
     * only indexable surface would be the one nobody gets.
     */
    private function wantsFragment(DiscoveryRequest $request): bool
    {
        return $request->ajax() || $request->header(self::PARTIAL_HEADER) !== null;
    }

    /**
     * The two tabs, exposed so the view and the query agree on the vocabulary.
     *
     * @return array<string, string>
     */
    public static function typeOptions(): array
    {
        return [
            '' => 'Everything',
            Theme::Store->value => 'Stores',
            Theme::Service->value => 'Services',
        ];
    }
}
