<?php

use App\Domains\Booking\Models\Service;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\Review;
use App\Domains\Seller\Enums\ReviewStatus;
use App\Domains\Seller\Models\Seller;
use App\Domains\Seller\Models\StorePage;
use App\Shared\Support\StoreUrl;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

/**
 * A published store page with one active product, returned as [seller, page, product].
 *
 * @return array{0: Seller, 1: StorePage, 2: Product}
 */
function reviewedProduct(): array
{
    [$seller, $page] = sellerWithPage([
        'theme' => 'store',
        'status' => 'published',
        'published_at' => now(),
    ]);

    $product = Product::factory()->create([
        'store_page_id' => $page->id,
        'seller_id' => $seller->id,
        'slug' => 'ankara-print',
        'name' => 'Ankara Print',
        'status' => 'active',
    ]);

    return [$seller, $page, $product];
}

it('does not publish a review on submission', function () {
    [$seller, $page, $product] = reviewedProduct();

    post(storeUrl($page->slug, '/reviews/product/ankara-print'), [
        'author_name' => 'Chidi Okeke',
        'rating' => 5,
        'body' => 'The cloth is exactly as pictured and it arrived fast.',
    ])->assertRedirect();

    $review = Review::withoutGlobalScopes()->sole();

    expect($review->status)->toBe(ReviewStatus::Pending)
        // A pending review has no publish date: "newest first" has to mean newest
        // *published*, or a seller works their queue in reverse.
        ->and($review->published_at)->toBeNull()
        ->and($review->reviewable_type)->toBe($product->getMorphClass())
        ->and($review->reviewable_id)->toBe($product->id);

    // The score must not have moved. Nothing is approved yet.
    expect($product->fresh()->review_count)->toBe(0)
        ->and($product->fresh()->rating)->toBe(0.0);
});

it('tells the person their review is waiting, rather than silently dropping it', function () {
    [$seller, $page] = reviewedProduct();

    post(storeUrl($page->slug, '/reviews/product/ankara-print'), [
        'author_name' => 'Chidi Okeke',
        'rating' => 4,
        'body' => 'Good quality, would buy again.',
    ])->assertSessionHas('review_sent');
});

it('refuses a rating outside one to five', function () {
    [$seller, $page] = reviewedProduct();

    post(storeUrl($page->slug, '/reviews/product/ankara-print'), [
        'author_name' => 'Chidi Okeke',
        'rating' => 6,
        'body' => 'Six stars would be even better.',
    ])->assertSessionHasErrors('rating');

    expect(Review::withoutGlobalScopes()->count())->toBe(0);
});

it('refuses a review with nothing in it', function () {
    [$seller, $page] = reviewedProduct();

    // "Great" is a rating, not a review. A wall of one-word rows makes the list
    // worthless to the next reader.
    post(storeUrl($page->slug, '/reviews/product/ankara-print'), [
        'author_name' => 'Chidi Okeke',
        'rating' => 5,
        'body' => 'Great',
    ])->assertSessionHasErrors('body');
});

it('will not let a review be attached to another seller\'s product', function () {
    [$seller, $page] = reviewedProduct();

    $foreign = Product::factory()->create([
        'store_page_id' => $page->id,
        'seller_id' => $page->seller_id,
        'slug' => 'someone-elses',
        'status' => 'active',
    ]);

    // A crafted request cannot reach a product that is not on this page.
    post(storeUrl($page->slug, '/reviews/product/does-not-exist'), [
        'author_name' => 'Chidi Okeke',
        'rating' => 5,
        'body' => 'A review of something I have never seen.',
    ])->assertNotFound();

    expect(Review::withoutGlobalScopes()->count())->toBe(0);
});

it('sanitises the body before it is stored', function () {
    [$seller, $page] = reviewedProduct();

    post(storeUrl($page->slug, '/reviews/product/ankara-print'), [
        'author_name' => 'Chidi Okeke',
        'rating' => 5,
        'body' => 'Lovely cloth. <script>alert(1)</script>',
    ])->assertRedirect();

    // Buyer-authored text lands on a public page. It is cleaned on the way in so
    // every reader of the row does not have to remember to escape it.
    expect(Review::withoutGlobalScopes()->sole()->body)->not->toContain('<script');
});

it('recalculates the score when the seller approves', function () {
    [$seller, $page, $product] = reviewedProduct();

    post(storeUrl($page->slug, '/reviews/product/ankara-print'), [
        'author_name' => 'Chidi Okeke',
        'rating' => 5,
        'body' => 'The cloth is exactly as pictured.',
    ]);

    actingAs($seller)->post('/reviews/'.Review::withoutGlobalScopes()->sole()->id.'/approve')
        ->assertRedirect();

    $fresh = $product->fresh();

    expect($fresh->review_count)->toBe(1)
        ->and($fresh->rating)->toBe(5.0);
});

it('takes the score back down when a review is declined', function () {
    [$seller, $page, $product] = reviewedProduct();

    post(storeUrl($page->slug, '/reviews/product/ankara-print'), [
        'author_name' => 'Chidi Okeke',
        'rating' => 1,
        'body' => 'Arrived damaged and nobody answered me.',
    ]);

    actingAs($seller)->post('/reviews/'.Review::withoutGlobalScopes()->sole()->id.'/approve');

    expect($product->fresh()->rating)->toBe(1.0);

    actingAs($seller)->post('/reviews/'.Review::withoutGlobalScopes()->sole()->id.'/reject');

    // A score that only ever climbs is the one number nobody should trust.
    expect($product->fresh()->review_count)->toBe(0)
        ->and($product->fresh()->rating)->toBe(0.0);
});

it('never lets one seller reach another seller\'s review', function () {
    [$seller, $page] = reviewedProduct();

    $other = Seller::factory()->create();

    $foreignReview = Review::factory()->forBusiness()->create([
        'seller_id' => $other->id,
        'store_page_id' => StorePage::factory()->for($other)->create()->id,
        'reviewable_id' => $page->id,
        'author_name' => 'Someone',
        'rating' => 1,
        'body' => 'A review belonging to a different seller.',
        'status' => ReviewStatus::Pending,
    ]);

    // 404 rather than 403: guessing an id should not confirm it exists.
    actingAs($seller)->post("/reviews/{$foreignReview->id}/approve")->assertNotFound();
});

it('shows the seller only their own queue', function () {
    [$seller, $page] = reviewedProduct();

    Review::factory()->forBusiness()->create([
        'seller_id' => $page->seller_id,
        'store_page_id' => $page->id,
        'reviewable_id' => $page->id,
        'author_name' => 'Chidi',
        'rating' => 4,
        'body' => 'My own pending review.',
        'status' => ReviewStatus::Pending,
    ]);

    // Somebody else's review, on another seller's page, with the same body — so
    // a leak would show as two rows rather than as a row that reads the same.
    $other = Seller::factory()->create();

    Review::factory()->forBusiness()->create([
        'seller_id' => $other->id,
        'store_page_id' => StorePage::factory()->for($other)->create()->id,
        'reviewable_id' => $other->id,
        'author_name' => 'Chidi',
        'rating' => 1,
        'body' => 'My own pending review.',
        'status' => ReviewStatus::Pending,
    ]);

    $html = actingAs($seller)->get('/reviews')->assertOk()->getContent();

    expect($html)->toContain('My own pending review.')
        ->and(substr_count($html, 'My own pending review.'))->toBe(1);
});

it('says so when the queue is empty rather than rendering a blank grid', function () {
    [$seller] = reviewedProduct();

    $html = actingAs($seller)->get('/reviews')->assertOk()->getContent();

    expect($html)->toContain('No reviews waiting.');
});

it('renders a published review on the product page', function () {
    [$seller, $page, $product] = reviewedProduct();

    Review::factory()->create([
        'seller_id' => $page->seller_id,
        'store_page_id' => $page->id,
        'reviewable_type' => $product->getMorphClass(),
        'reviewable_id' => $product->id,
        'author_name' => 'Chidi Okeke',
        'rating' => 5,
        'body' => 'The cloth is exactly as pictured.',
        'status' => ReviewStatus::Approved,
        'published_at' => now()->subDay(),
    ]);

    $product->recalculateRating();

    get(storeUrl($page->slug, '/products/ankara-print'))
        ->assertOk()
        ->assertSee('The cloth is exactly as pictured.')
        ->assertSee('Chidi Okeke');
});

it('does not render a pending review on the product page', function () {
    [$seller, $page, $product] = reviewedProduct();

    Review::factory()->create([
        'seller_id' => $page->seller_id,
        'store_page_id' => $page->id,
        'reviewable_type' => $product->getMorphClass(),
        'reviewable_id' => $product->id,
        'author_name' => 'Chidi Okeke',
        'rating' => 1,
        'body' => 'Something the seller has not read yet.',
        'status' => ReviewStatus::Pending,
    ]);

    get(storeUrl($page->slug, '/products/ankara-print'))
        ->assertOk()
        ->assertDontSee('Something the seller has not read yet.');
});

it('offers the review form on the product page', function () {
    [$seller, $page] = reviewedProduct();

    $html = get(storeUrl($page->slug, '/products/ankara-print'))->assertOk()->getContent();

    expect($html)->toContain(StoreUrl::path('storefront.product.review', ['slug' => $page->slug, 'productSlug' => 'ankara-print']), escape: false)
        ->and($html)->toContain('Leave a review');
});

it('opens the product page on the reviews tab after a submission', function () {
    [$seller, $page] = reviewedProduct();

    // The confirmation is printed inside the reviews tab. Landing back on the
    // details tab put it somewhere the shopper was not looking, so a
    // successful submission looked like one that had quietly failed.
    post(storeUrl($page->slug, '/reviews/product/ankara-print'), [
        'author_name' => 'Chidi Okeke',
        'rating' => 5,
        'body' => 'The cloth is exactly as pictured.',
    ])->assertRedirect();

    $html = get(storeUrl($page->slug, '/products/ankara-print'))->assertOk()->getContent();

    expect($html)->toContain('&quot;tab&quot;:&quot;reviews&quot;')
        ->and($html)->toContain('Thank you. Your review will appear once the seller has read it.');
});

it('opens the product page on the reviews tab when the submission was rejected', function () {
    [$seller, $page] = reviewedProduct();

    // No assertSessionHasErrors here: it reads the flashed bag off the session
    // to check it, which leaves nothing for the page under test to find.
    post(storeUrl($page->slug, '/reviews/product/ankara-print'), [
        'author_name' => 'C',
        'rating' => 5,
        'body' => 'too short',
    ])->assertRedirect();

    $html = get(storeUrl($page->slug, '/products/ankara-print'))->assertOk()->getContent();

    expect($html)->toContain('&quot;tab&quot;:&quot;reviews&quot;')
        ->and($html)->toContain('role="alert"');
});

it('lets the buyer reach the review dialog from the home page', function () {
    [$seller, $page] = reviewedProduct();

    // The block only renders once it has something to show, so the block and
    // the approved row both have to be here for the button to exist at all.
    $page->blocks()->create([
        'page' => 'home',
        'key' => 'reviews',
        'content' => ['heading' => 'What buyers say'],
    ]);

    Review::factory()->approved()->forBusiness()->create([
        'seller_id' => $page->seller_id,
        'store_page_id' => $page->id,
        'reviewable_id' => $page->id,
    ]);

    $page->recalculateRating();

    $html = get(storeUrl($page->slug, ''))->assertOk()->getContent();

    // Alpine scopes cascade downwards. A button that sets `reviewModalOpen`
    // can only reach a state declared above it, and one declared below it
    // resolves to nothing — the dialog then simply never opens.
    $document = new DOMDocument;
    @$document->loadHTML($html);

    $button = (new DOMXPath($document))->query("//button[contains(text(), 'Write a Review')]")->item(0);

    expect($button)->not->toBeNull();

    $scoped = false;

    for ($node = $button; $node instanceof DOMElement; $node = $node->parentElement) {
        if (str_contains($node->getAttribute('x-data'), 'reviewModalOpen')) {
            $scoped = true;
            break;
        }
    }

    expect($scoped)->toBeTrue();
});

it('accepts and shows a review on a service', function () {
    [$seller, $page] = sellerWithPage([
        'theme' => 'service',
        'status' => 'published',
        'published_at' => now(),
    ]);

    $service = Service::withoutGlobalScopes()->create([
        'seller_id' => $seller->id,
        'store_page_id' => $page->id,
        'name' => 'Knotless Braids',
        'slug' => 'knotless-braids',
        'is_active' => true,
    ]);

    post(storeUrl($page->slug, '/reviews/service/knotless-braids'), [
        'author_name' => 'Ngozi James',
        'rating' => 5,
        'body' => 'Lasted three weeks through a wedding.',
    ])->assertRedirect();

    $review = Review::withoutGlobalScopes()->sole();

    expect($review->reviewable_id)->toBe($service->id)
        ->and($review->status)->toBe(ReviewStatus::Pending);

    actingAs($seller)->post("/reviews/{$review->id}/approve");

    expect($service->fresh()->review_count)->toBe(1)
        ->and($service->fresh()->rating)->toBe(5.0);
});

it('shows an anonymous review as a customer, never as an empty name', function () {
    [$seller, $page, $product] = reviewedProduct();

    $review = Review::factory()->create([
        'seller_id' => $page->seller_id,
        'store_page_id' => $page->id,
        'reviewable_type' => $product->getMorphClass(),
        'reviewable_id' => $product->id,
        'author_name' => 'Ngozi James',
        'rating' => 4,
        'body' => 'Decent, though the delivery was late.',
        'is_anonymous' => true,
        'status' => ReviewStatus::Approved,
        'published_at' => now(),
    ]);

    // An unattributed review reads as unattributed content. "A customer" is
    // honest and is not a name anyone can dox.
    expect($review->authorLabel())->toBe('A customer');

    get(storeUrl($page->slug, '/products/ankara-print'))
        ->assertOk()
        ->assertSee('A customer')
        ->assertDontSee('Ngozi James');
});

it('pins a review without un-publishing it', function () {
    [$seller, $page, $product] = reviewedProduct();

    $review = Review::factory()->create([
        'seller_id' => $page->seller_id,
        'store_page_id' => $page->id,
        'reviewable_type' => $product->getMorphClass(),
        'reviewable_id' => $product->id,
        'author_name' => 'Chidi',
        'rating' => 5,
        'body' => 'Worth saying twice.',
        'status' => ReviewStatus::Approved,
        'published_at' => now(),
    ]);

    actingAs($seller)->post("/reviews/{$review->id}/feature");

    $review->refresh();

    expect($review->is_featured)->toBeTrue()
        ->and($review->status)->toBe(ReviewStatus::Approved);
});
