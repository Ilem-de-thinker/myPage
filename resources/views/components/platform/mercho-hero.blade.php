{{--
    Mercho hero illustration.

    A self-contained, transparent inline SVG that builds a storefront browser
    window from scratch on a nine-second loop. Every colour is read from a
    `--mercho-*` custom property with a warm editorial fallback, so a parent
    can retheme the illustration without touching this file.

    The motion is deliberately slow and editorial: the window assembles, the
    headline types, the image fades in, the buttons spring, the listing cards
    stagger, and a gold Mercho spark drifts through the frame. A grain layer and
    a soft radial glow keep it from looking like flat vector clip art.

    Reduced motion is honoured twice: the OS preference and the platform's
    `data-reduce-motion` switch both stop every animation and show the finished
    composition instead.
--}}

@props(['class' => ''])

<div
    {{ $attributes->merge(['class' => 'mercho-hero pointer-events-none relative w-full ' . $class]) }}
    role="img"
    aria-label="An animated illustration of a storefront being assembled, with Mercho guiding the work."
>
    <svg
        viewBox="0 0 680 680"
        fill="none"
        xmlns="http://www.w3.org/2000/svg"
        class="h-auto w-full"
        preserveAspectRatio="xMidYMid meet"
        aria-hidden="true"
        focusable="false"
    >
        <defs>
            {{-- The window is almost paper, but a little translucent so the
                 cards behind it read as layers rather than a flat stack. --}}
            <linearGradient id="mercho-window" x1="90" y1="120" x2="550" y2="512" gradientUnits="userSpaceOnUse">
                <stop offset="0" stop-color="#ffffff" stop-opacity="0.98"/>
                <stop offset="1" stop-color="#f8f5ff" stop-opacity="0.92"/>
            </linearGradient>

            <linearGradient id="mercho-image" x1="118" y1="282" x2="338" y2="394" gradientUnits="userSpaceOnUse">
                <stop offset="0" stop-color="var(--mercho-indigo, #4f46e5)"/>
                <stop offset="0.55" stop-color="var(--mercho-blue, #2f7cf6)"/>
                <stop offset="1" stop-color="var(--mercho-gold, #e3a93f)"/>
            </linearGradient>

            <linearGradient id="mercho-gold" x1="540" y1="134" x2="572" y2="168" gradientUnits="userSpaceOnUse">
                <stop offset="0" stop-color="var(--mercho-gold-soft, #f6d488)"/>
                <stop offset="1" stop-color="var(--mercho-gold, #e3a93f)"/>
            </linearGradient>

            {{-- A warm halo behind the window. Kept very low so the page paper
                 still reads through the transparent SVG. --}}
            <radialGradient id="mercho-glow" cx="50%" cy="48%" r="52%">
                <stop offset="0" stop-color="var(--mercho-gold, #e3a93f)" stop-opacity="0.18"/>
                <stop offset="0.55" stop-color="var(--mercho-indigo, #4f46e5)" stop-opacity="0.08"/>
                <stop offset="1" stop-color="var(--mercho-indigo, #4f46e5)" stop-opacity="0"/>
            </radialGradient>

            <radialGradient id="mercho-orb-halo" cx="50%" cy="50%" r="50%">
                <stop offset="0" stop-color="var(--mercho-gold, #e3a93f)" stop-opacity="0.38"/>
                <stop offset="1" stop-color="var(--mercho-gold, #e3a93f)" stop-opacity="0"/>
            </radialGradient>

            <filter id="mercho-shadow" x="-20%" y="-20%" width="140%" height="150%">
                <feDropShadow dx="0" dy="20" stdDeviation="18" flood-color="#1c1b33" flood-opacity="0.16"/>
            </filter>

            <filter id="mercho-soft" x="-60%" y="-60%" width="220%" height="220%">
                <feGaussianBlur stdDeviation="10"/>
            </filter>

            {{-- Fine paper grain. The turbulence output is desaturated and then
                 given a very low alpha, so it never becomes visible noise. --}}
            <filter id="mercho-grain" x="0" y="0" width="100%" height="100%">
                <feTurbulence type="fractalNoise" baseFrequency="0.85" numOctaves="2" stitchTiles="stitch"/>
                <feColorMatrix type="saturate" values="0"/>
                <feComponentTransfer>
                    <feFuncA type="linear" slope="0.055"/>
                </feComponentTransfer>
            </filter>

            {{-- Reveals the headline from left to right like a typed line. --}}
            <clipPath id="mercho-type-clip">
                <rect class="mercho-type-rect" x="118" y="194" width="250" height="58"/>
            </clipPath>

            {{-- Keeps the abstract hero artwork inside its rounded frame. --}}
            <clipPath id="mercho-image-clip">
                <rect x="118" y="282" width="220" height="112" rx="16"/>
            </clipPath>

            {{-- Keeps the browser content inside the window body. --}}
            <clipPath id="mercho-content-clip">
                <rect x="90" y="172" width="460" height="340" rx="18"/>
            </clipPath>
        </defs>

        @verbatim
        <style>
            /* The palette. Every value is a fallback so a parent can override
               it with the same custom property. */
            .mercho-hero {
                --mercho-ink: #1c1b33;
                --mercho-indigo: #4f46e5;
                --mercho-blue: #2f7cf6;
                --mercho-gold: #e3a93f;
                --mercho-gold-soft: #f6d488;
                --mercho-paper: #fbf7ee;
                --mercho-card: #fffdf9;
                --mercho-line: rgba(28, 27, 51, 0.12);
                --mercho-muted: rgba(28, 27, 51, 0.46);
            }

            .mercho-scene {
                animation: mercho-scene 9s ease-in-out infinite;
                transform-origin: center;
            }

            /* The whole composition fades in, holds, then fades out so the
               nine-second loop restarts without a hard cut. */
            @keyframes mercho-scene {
                0%   { opacity: 0; }
                5%   { opacity: 1; }
                90%  { opacity: 1; }
                100% { opacity: 0; }
            }

            .mercho-build-1,
            .mercho-build-2,
            .mercho-build-3,
            .mercho-build-4,
            .mercho-build-5 {
                transform-box: fill-box;
                transform-origin: center;
            }

            .mercho-build-1 { animation: mercho-build-1 9s cubic-bezier(0.22, 1, 0.36, 1) infinite; }
            .mercho-build-2 { animation: mercho-build-2 9s cubic-bezier(0.22, 1, 0.36, 1) infinite; }
            .mercho-build-3 { animation: mercho-build-3 9s cubic-bezier(0.22, 1, 0.36, 1) infinite; }
            .mercho-build-4 { animation: mercho-build-4 9s cubic-bezier(0.22, 1, 0.36, 1) infinite; }
            .mercho-build-5 { animation: mercho-build-5 9s cubic-bezier(0.22, 1, 0.36, 1) infinite; }

            @keyframes mercho-build-1 {
                0%, 1%   { opacity: 0; transform: translateY(18px) scale(0.97); }
                10%, 100% { opacity: 1; transform: translateY(0) scale(1); }
            }

            @keyframes mercho-build-2 {
                0%, 4%   { opacity: 0; transform: translateY(16px); }
                13%, 100% { opacity: 1; transform: translateY(0); }
            }

            @keyframes mercho-build-3 {
                0%, 7%   { opacity: 0; transform: translateY(14px); }
                16%, 100% { opacity: 1; transform: translateY(0); }
            }

            @keyframes mercho-build-4 {
                0%, 16%  { opacity: 0; transform: translateY(10px); }
                25%, 100% { opacity: 1; transform: translateY(0); }
            }

            @keyframes mercho-build-5 {
                0%, 21%  { opacity: 0; transform: translateY(8px); }
                30%, 100% { opacity: 1; transform: translateY(0); }
            }

            /* Typing reveal for the headline. The clip rect is scaled from the
               left edge; nothing about the glyph geometry changes. */
            .mercho-type-rect {
                transform-box: fill-box;
                transform-origin: left center;
                animation: mercho-type 9s cubic-bezier(0.22, 1, 0.36, 1) infinite;
            }

            @keyframes mercho-type {
                0%, 10%  { transform: scaleX(0); }
                27%, 100% { transform: scaleX(1); }
            }

            .mercho-caret {
                animation: mercho-caret 1.1s steps(1, end) infinite;
            }

            @keyframes mercho-caret {
                0%, 49%  { opacity: 1; }
                50%, 100% { opacity: 0; }
            }

            /* The hero image fades up after the copy has typed in. */
            .mercho-image {
                animation: mercho-image 9s cubic-bezier(0.22, 1, 0.36, 1) infinite;
            }

            @keyframes mercho-image {
                0%, 26%  { opacity: 0; transform: translateY(8px); }
                38%, 100% { opacity: 1; transform: translateY(0); }
            }

            /* Buttons arrive with a restrained spring, never a cartoon bounce. */
            .mercho-buttons {
                transform-box: fill-box;
                transform-origin: center;
                animation: mercho-buttons 9s cubic-bezier(0.34, 1.4, 0.64, 1) infinite;
            }

            @keyframes mercho-buttons {
                0%, 36%  { opacity: 0; transform: scale(0.72); }
                47%      { opacity: 1; transform: scale(1.03); }
                52%, 100% { opacity: 1; transform: scale(1); }
            }

            /* The four listing cards stagger in like a rendered collection. */
            .mercho-card-1,
            .mercho-card-2,
            .mercho-card-3,
            .mercho-card-4,
            .mercho-card-5 {
                transform-box: fill-box;
                transform-origin: center;
            }

            .mercho-card-1 { animation: mercho-card-1 9s cubic-bezier(0.22, 1, 0.36, 1) infinite; }
            .mercho-card-2 { animation: mercho-card-2 9s cubic-bezier(0.22, 1, 0.36, 1) infinite; }
            .mercho-card-3 { animation: mercho-card-3 9s cubic-bezier(0.22, 1, 0.36, 1) infinite; }
            .mercho-card-4 { animation: mercho-card-4 9s cubic-bezier(0.22, 1, 0.36, 1) infinite; }
            .mercho-card-5 { animation: mercho-card-5 9s cubic-bezier(0.22, 1, 0.36, 1) infinite; }

            @keyframes mercho-card-1 {
                0%, 40%  { opacity: 0; transform: translateY(12px); }
                49%, 100% { opacity: 1; transform: translateY(0); }
            }
            @keyframes mercho-card-2 {
                0%, 44%  { opacity: 0; transform: translateY(12px); }
                53%, 100% { opacity: 1; transform: translateY(0); }
            }
            @keyframes mercho-card-3 {
                0%, 48%  { opacity: 0; transform: translateY(12px); }
                57%, 100% { opacity: 1; transform: translateY(0); }
            }
            @keyframes mercho-card-4 {
                0%, 52%  { opacity: 0; transform: translateY(12px); }
                61%, 100% { opacity: 1; transform: translateY(0); }
            }
            @keyframes mercho-card-5 {
                0%, 56%  { opacity: 0; transform: translateY(10px); }
                65%, 100% { opacity: 1; transform: translateY(0); }
            }

            /* The gold Mercho spark drifts through the frame on a slow arc.
               The trail circles share the path with a negative delay, so they
               fall behind the spark and fade out as they go. */
            .mercho-orb,
            .mercho-trail-1,
            .mercho-trail-2,
            .mercho-trail-3 {
                animation: mercho-drift 9s ease-in-out infinite;
            }

            .mercho-trail-1 { animation-delay: -0.35s; }
            .mercho-trail-2 { animation-delay: -0.7s; }
            .mercho-trail-3 { animation-delay: -1.05s; }

            @keyframes mercho-drift {
                0%, 100% { transform: translate(0, 0); }
                25%      { transform: translate(-18px, -42px); }
                50%      { transform: translate(-4px, -96px); }
                75%      { transform: translate(20px, -46px); }
            }

            /* Background cards breathe independently of the build sequence. */
            .mercho-float-1,
            .mercho-float-2,
            .mercho-float-3,
            .mercho-float-4 {
                animation: mercho-float 9s ease-in-out infinite;
            }

            .mercho-float-2 { animation-delay: -2.2s; }
            .mercho-float-3 { animation-delay: -4.4s; }
            .mercho-float-4 { animation-delay: -6.6s; }

            @keyframes mercho-float {
                0%, 100% { transform: translateY(0); }
                50%      { transform: translateY(-7px); }
            }

            /* Reduced motion: show the finished composition, stop everything. */
            @media (prefers-reduced-motion: reduce) {
                .mercho-scene,
                .mercho-scene * {
                    animation: none !important;
                }

                .mercho-scene {
                    opacity: 1;
                }

                .mercho-type-rect {
                    transform: scaleX(1);
                }

                .mercho-image,
                .mercho-buttons,
                .mercho-card-1,
                .mercho-card-2,
                .mercho-card-3,
                .mercho-card-4,
                .mercho-card-5 {
                    opacity: 1;
                    transform: none;
                }
            }

            [data-reduce-motion="true"] .mercho-scene,
            [data-reduce-motion="true"] .mercho-scene * {
                animation: none !important;
            }

            [data-reduce-motion="true"] .mercho-scene {
                opacity: 1;
            }

            [data-reduce-motion="true"] .mercho-type-rect {
                transform: scaleX(1);
            }

            [data-reduce-motion="true"] .mercho-image,
            [data-reduce-motion="true"] .mercho-buttons,
            [data-reduce-motion="true"] .mercho-card-1,
            [data-reduce-motion="true"] .mercho-card-2,
            [data-reduce-motion="true"] .mercho-card-3,
            [data-reduce-motion="true"] .mercho-card-4,
            [data-reduce-motion="true"] .mercho-card-5 {
                opacity: 1;
                transform: none;
            }
        </style>
        @endverbatim

        {{-- Warm halo and paper grain sit behind everything. --}}
        <ellipse cx="340" cy="326" rx="312" ry="272" fill="url(#mercho-glow)"/>
        <rect width="680" height="680" filter="url(#mercho-grain)" opacity="0.6"/>

        <g class="mercho-scene">

            {{-- ── Floating UI cards behind the window ───────────────────── --}}

            {{-- Testimonial: a small review card peeking out on the left. --}}
            <g class="mercho-build-2">
                <g transform="rotate(-8 112 152)">
                    <g class="mercho-float-1">
                        <rect x="24" y="96" width="176" height="112" rx="18" fill="var(--mercho-card, #fffdf9)" fill-opacity="0.9" stroke="var(--mercho-line, rgba(28,27,51,0.12))"/>
                        <circle cx="50" cy="130" r="14" fill="var(--mercho-gold-soft, #f6d488)"/>
                        <path d="M42 138c2-7 14-7 16 0" stroke="var(--mercho-ink, #1c1b33)" stroke-width="2" stroke-linecap="round" opacity="0.55"/>
                        <rect x="74" y="122" width="92" height="8" rx="4" fill="var(--mercho-ink, #1c1b33)" opacity="0.7"/>
                        <rect x="74" y="138" width="66" height="6" rx="3" fill="var(--mercho-muted, rgba(28,27,51,0.46))"/>
                        <g fill="var(--mercho-gold, #e3a93f)">
                            <circle cx="34" cy="172" r="2.6"/>
                            <circle cx="48" cy="172" r="2.6"/>
                            <circle cx="62" cy="172" r="2.6"/>
                            <circle cx="76" cy="172" r="2.6"/>
                            <circle cx="90" cy="172" r="2.6"/>
                        </g>
                        <rect x="108" y="168" width="60" height="6" rx="3" fill="var(--mercho-muted, rgba(28,27,51,0.46))" opacity="0.7"/>
                    </g>
                </g>
            </g>

            {{-- Pricing chip peeking out on the top right. --}}
            <g class="mercho-build-3">
                <g transform="rotate(7 571 120)">
                    <g class="mercho-float-2">
                        <rect x="486" y="72" width="170" height="96" rx="18" fill="var(--mercho-card, #fffdf9)" fill-opacity="0.9" stroke="var(--mercho-line, rgba(28,27,51,0.12))"/>
                        <text x="504" y="112" font-family="var(--platform-text, sans-serif)" font-size="19" font-weight="700" fill="var(--mercho-ink, #1c1b33)">₦12,500</text>
                        <text x="504" y="130" font-family="var(--platform-text, sans-serif)" font-size="9" font-weight="600" fill="var(--mercho-muted, rgba(28,27,51,0.46))">Growth plan</text>
                        <rect x="504" y="142" width="88" height="6" rx="3" fill="var(--mercho-muted, rgba(28,27,51,0.46))" opacity="0.55"/>
                        <circle cx="626" cy="102" r="12" fill="var(--mercho-indigo, #4f46e5)"/>
                        <path d="m620.5 102 3.8 3.8 7.2-7.6" stroke="#ffffff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </g>
                </g>
            </g>

            {{-- Chat bubble peeking out on the bottom left. --}}
            <g class="mercho-build-3">
                <g transform="rotate(6 114 482)">
                    <g class="mercho-float-3">
                        <rect x="28" y="438" width="172" height="88" rx="20" fill="var(--mercho-indigo, #4f46e5)"/>
                        <path d="M58 526 46 542 76 526Z" fill="var(--mercho-indigo, #4f46e5)"/>
                        <rect x="46" y="458" width="104" height="7" rx="3.5" fill="#ffffff" opacity="0.85"/>
                        <rect x="46" y="474" width="132" height="6" rx="3" fill="#ffffff" opacity="0.55"/>
                        <rect x="46" y="488" width="78" height="6" rx="3" fill="#ffffff" opacity="0.4"/>
                        <circle cx="166" cy="494" r="4" fill="var(--mercho-gold-soft, #f6d488)"/>
                    </g>
                </g>
            </g>

            {{-- Mini analytics card peeking out on the bottom right. --}}
            <g class="mercho-build-4">
                <g transform="rotate(-7 558 500)">
                    <g class="mercho-float-4">
                        <rect x="466" y="446" width="184" height="108" rx="18" fill="var(--mercho-card, #fffdf9)" fill-opacity="0.9" stroke="var(--mercho-line, rgba(28,27,51,0.12))"/>
                        <rect x="482" y="462" width="60" height="6" rx="3" fill="var(--mercho-muted, rgba(28,27,51,0.46))" opacity="0.7"/>
                        <g>
                            <rect x="486" y="510" width="14" height="26" rx="4" fill="var(--mercho-blue, #2f7cf6)" opacity="0.75"/>
                            <rect x="510" y="498" width="14" height="38" rx="4" fill="var(--mercho-indigo, #4f46e5)" opacity="0.75"/>
                            <rect x="534" y="516" width="14" height="20" rx="4" fill="var(--mercho-blue, #2f7cf6)" opacity="0.55"/>
                            <rect x="558" y="490" width="14" height="46" rx="4" fill="var(--mercho-indigo, #4f46e5)" opacity="0.85"/>
                            <rect x="582" y="504" width="14" height="32" rx="4" fill="var(--mercho-blue, #2f7cf6)" opacity="0.7"/>
                            <rect x="606" y="482" width="14" height="54" rx="4" fill="var(--mercho-gold, #e3a93f)"/>
                        </g>
                        <line x1="482" y1="536" x2="634" y2="536" stroke="var(--mercho-line, rgba(28,27,51,0.12))" stroke-width="1.5"/>
                    </g>
                </g>
            </g>

            {{-- ── The layered browser window ────────────────────────────── --}}

            {{-- Back pane, rotated furthest. --}}
            <g class="mercho-build-2">
                <rect x="132" y="148" width="420" height="344" rx="28" fill="var(--mercho-indigo, #4f46e5)" opacity="0.1" transform="rotate(-7 342 320)"/>
            </g>

            {{-- Middle pane. --}}
            <g class="mercho-build-3">
                <rect x="112" y="134" width="440" height="362" rx="28" fill="var(--mercho-blue, #2f7cf6)" opacity="0.12" transform="rotate(-3.5 342 320)"/>
            </g>

            {{-- Front window. --}}
            <g class="mercho-build-1">
                <g filter="url(#mercho-shadow)">
                    <rect x="90" y="120" width="460" height="392" rx="24" fill="url(#mercho-window)" stroke="var(--mercho-line, rgba(28,27,51,0.12))" stroke-width="1.5"/>
                </g>

                {{-- Browser chrome. --}}
                <path d="M90 144a24 24 0 0 1 24-24h412a24 24 0 0 1 24 24v28H90z" fill="var(--mercho-paper, #fbf7ee)"/>
                <line x1="90" y1="172" x2="550" y2="172" stroke="var(--mercho-line, rgba(28,27,51,0.12))" stroke-width="1.5"/>
                <circle cx="114" cy="146" r="4" fill="var(--mercho-gold, #e3a93f)"/>
                <circle cx="130" cy="146" r="4" fill="var(--mercho-blue, #2f7cf6)"/>
                <circle cx="146" cy="146" r="4" fill="var(--mercho-indigo, #4f46e5)"/>
                <rect x="182" y="136" width="232" height="20" rx="10" fill="var(--mercho-card, #fffdf9)" fill-opacity="0.7" stroke="var(--mercho-line, rgba(28,27,51,0.12))"/>
                <path d="M194 146v-2.5a4 4 0 0 1 8 0v2.5M193 146h10v6h-10z" stroke="var(--mercho-muted, rgba(28,27,51,0.46))" stroke-width="1.2" stroke-linejoin="round"/>
                <text x="300" y="150" text-anchor="middle" font-family="var(--platform-mono, monospace)" font-size="9" fill="var(--mercho-muted, rgba(28,27,51,0.46))">mercho.mypage.ng</text>

                {{-- Window content, clipped to the body. --}}
                <g clip-path="url(#mercho-content-clip)">

                    {{-- A small live badge. --}}
                    <g class="mercho-build-5">
                        <rect x="438" y="176" width="76" height="16" rx="8" fill="var(--mercho-gold, #e3a93f)" opacity="0.16"/>
                        <circle cx="450" cy="184" r="3" fill="var(--mercho-gold, #e3a93f)"/>
                        <text x="460" y="187" font-family="var(--platform-text, sans-serif)" font-size="8" font-weight="700" letter-spacing="0.8" fill="var(--mercho-gold, #e3a93f)">LIVE</text>
                    </g>

                    {{-- Headline, revealed as if typed. --}}
                    <g clip-path="url(#mercho-type-clip)">
                        <rect x="118" y="198" width="214" height="16" rx="8" fill="var(--mercho-ink, #1c1b33)"/>
                        <rect x="118" y="224" width="150" height="16" rx="8" fill="var(--mercho-indigo, #4f46e5)"/>
                    </g>
                    <rect class="mercho-caret" x="340" y="198" width="2.5" height="16" rx="1.25" fill="var(--mercho-indigo, #4f46e5)"/>

                    <g class="mercho-build-4">
                        <rect x="118" y="254" width="236" height="8" rx="4" fill="var(--mercho-ink, #1c1b33)" opacity="0.35"/>
                    </g>

                    {{-- Abstract hero image. --}}
                    <g class="mercho-image">
                        <rect x="118" y="282" width="220" height="112" rx="16" fill="url(#mercho-image)"/>
                        <g clip-path="url(#mercho-image-clip)">
                            <circle cx="292" cy="312" r="18" fill="var(--mercho-gold-soft, #f6d488)" opacity="0.9"/>
                            <path d="M118 372 166 330 204 356 246 314 338 392V394H118Z" fill="#ffffff" opacity="0.16"/>
                            <path d="M118 386 174 348 218 372 268 334 338 392V394H118Z" fill="var(--mercho-indigo, #4f46e5)" opacity="0.35"/>
                        </g>
                    </g>

                    {{-- Primary and secondary actions. --}}
                    <g class="mercho-buttons">
                        <rect x="118" y="414" width="94" height="28" rx="14" fill="var(--mercho-indigo, #4f46e5)"/>
                        <text x="165" y="432" text-anchor="middle" font-family="var(--platform-text, sans-serif)" font-size="10" font-weight="700" fill="#ffffff">Start</text>
                        <rect x="222" y="414" width="86" height="28" rx="14" fill="none" stroke="var(--mercho-indigo, #4f46e5)" stroke-width="1.5"/>
                        <text x="265" y="432" text-anchor="middle" font-family="var(--platform-text, sans-serif)" font-size="10" font-weight="700" fill="var(--mercho-indigo, #4f46e5)">Preview</text>
                    </g>

                    {{-- The listing grid that assembles card by card. --}}
                    <g class="mercho-card-1">
                        <rect x="358" y="198" width="76" height="80" rx="14" fill="var(--mercho-paper, #fbf7ee)" stroke="var(--mercho-line, rgba(28,27,51,0.12))"/>
                        <rect x="370" y="210" width="24" height="24" rx="8" fill="var(--mercho-indigo, #4f46e5)" opacity="0.85"/>
                        <rect x="370" y="244" width="48" height="6" rx="3" fill="var(--mercho-ink, #1c1b33)" opacity="0.5"/>
                        <rect x="370" y="256" width="34" height="5" rx="2.5" fill="var(--mercho-ink, #1c1b33)" opacity="0.28"/>
                    </g>

                    <g class="mercho-card-2">
                        <rect x="442" y="198" width="76" height="80" rx="14" fill="var(--mercho-paper, #fbf7ee)" stroke="var(--mercho-line, rgba(28,27,51,0.12))"/>
                        <rect x="454" y="210" width="24" height="24" rx="8" fill="var(--mercho-blue, #2f7cf6)" opacity="0.85"/>
                        <rect x="454" y="244" width="46" height="6" rx="3" fill="var(--mercho-ink, #1c1b33)" opacity="0.5"/>
                        <rect x="454" y="256" width="30" height="5" rx="2.5" fill="var(--mercho-ink, #1c1b33)" opacity="0.28"/>
                    </g>

                    <g class="mercho-card-3">
                        <rect x="358" y="286" width="76" height="80" rx="14" fill="var(--mercho-paper, #fbf7ee)" stroke="var(--mercho-line, rgba(28,27,51,0.12))"/>
                        <rect x="370" y="298" width="24" height="24" rx="8" fill="var(--mercho-gold, #e3a93f)" opacity="0.9"/>
                        <rect x="370" y="332" width="50" height="6" rx="3" fill="var(--mercho-ink, #1c1b33)" opacity="0.5"/>
                        <rect x="370" y="344" width="36" height="5" rx="2.5" fill="var(--mercho-ink, #1c1b33)" opacity="0.28"/>
                    </g>

                    <g class="mercho-card-4">
                        <rect x="442" y="286" width="76" height="80" rx="14" fill="var(--mercho-paper, #fbf7ee)" stroke="var(--mercho-line, rgba(28,27,51,0.12))"/>
                        <rect x="454" y="298" width="24" height="24" rx="8" fill="var(--mercho-indigo, #4f46e5)" opacity="0.55"/>
                        <rect x="454" y="332" width="44" height="6" rx="3" fill="var(--mercho-ink, #1c1b33)" opacity="0.5"/>
                        <rect x="454" y="344" width="28" height="5" rx="2.5" fill="var(--mercho-ink, #1c1b33)" opacity="0.28"/>
                    </g>

                    <g class="mercho-card-5">
                        <rect x="358" y="374" width="160" height="30" rx="15" fill="var(--mercho-indigo, #4f46e5)" opacity="0.08" stroke="var(--mercho-line, rgba(28,27,51,0.12))"/>
                        <circle cx="374" cy="389" r="5" fill="var(--mercho-blue, #2f7cf6)"/>
                        <rect x="386" y="385" width="92" height="7" rx="3.5" fill="var(--mercho-ink, #1c1b33)" opacity="0.32"/>
                        <rect x="486" y="385" width="20" height="7" rx="3.5" fill="var(--mercho-indigo, #4f46e5)" opacity="0.5"/>
                    </g>
                </g>
            </g>

            {{-- ── The Mercho spark and its fading trail ─────────────────── --}}

            <g class="mercho-trail">
                <circle class="mercho-trail-1" cx="556" cy="150" r="8" fill="var(--mercho-gold, #e3a93f)" opacity="0.18"/>
                <circle class="mercho-trail-2" cx="556" cy="150" r="6" fill="var(--mercho-gold, #e3a93f)" opacity="0.12"/>
                <circle class="mercho-trail-3" cx="556" cy="150" r="4" fill="var(--mercho-gold, #e3a93f)" opacity="0.08"/>
            </g>

            <g class="mercho-orb">
                <circle cx="556" cy="150" r="26" fill="url(#mercho-orb-halo)"/>
                <circle cx="556" cy="150" r="13" fill="var(--mercho-gold, #e3a93f)" opacity="0.3" filter="url(#mercho-soft)"/>
                <path d="M556 134l3 11 11 5-11 3-3 11-3-11-11-3 11-5z" fill="url(#mercho-gold)"/>
                <circle cx="556" cy="150" r="3.4" fill="#ffffff" opacity="0.92"/>
            </g>
        </g>
    </svg>
</div>
