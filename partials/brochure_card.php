<?php
$is_affiliate = mi_is_affiliate_brochure($b);
$cover_src = mi_brochure_cover_src($b['cover_image'] ?? '');
$product_url = trim((string)($b['affiliate_product_url'] ?? $b['amazon_product_url'] ?? $b['source_url'] ?? ''));
$product_title = trim((string)($b['affiliate_product_name'] ?? $b['amazon_product_name'] ?? '')) ?: (string)$b['title'];
$product_price = mi_price_label($b['affiliate_product_price'] ?? $b['amazon_product_price'] ?? null);
$product_rating = mi_rating_label($b['affiliate_product_rating'] ?? $b['amazon_product_rating'] ?? '');
$review_count = trim((string)($b['affiliate_review_count'] ?? $b['amazon_review_count'] ?? ''));
$affiliate_images = $is_affiliate ? mi_affiliate_image_list($b) : [];
if ($cover_src === '' && $affiliate_images) {
    $cover_src = $affiliate_images[0];
}
$card_classes = 'bg-white rounded-3xl border border-slate-100 overflow-hidden shadow-sm hover:shadow-xl hover:-translate-y-1.5 transition-all duration-300 group flex flex-col relative';
?>

<div class="<?= $card_classes ?> <?= $is_affiliate ? '' : 'cursor-pointer' ?>"
     <?php if (!$is_affiliate): ?>onclick="window.location='viewer.php?id=<?= (int)$b['id'] ?>'"<?php endif; ?>>
    <div class="relative aspect-[3/4] bg-slate-900/5 overflow-hidden">
        <?php if ($is_affiliate && count($affiliate_images) > 1): ?>
            <div class="absolute inset-0 bg-white" data-affiliate-slider data-active="0">
                <?php foreach ($affiliate_images as $index => $image_src): ?>
                    <img src="<?= htmlspecialchars($image_src) ?>"
                         class="absolute inset-0 w-full h-full object-contain bg-white p-6 transition-opacity duration-700 <?= $index === 0 ? 'opacity-100' : 'opacity-0' ?>"
                         data-affiliate-slide
                         alt="<?= htmlspecialchars($product_title) ?>"
                         <?= $index === 0 ? $lazyLoading : 'loading="lazy"' ?>
                         onerror="this.style.display='none'">
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <img src="<?= htmlspecialchars($cover_src) ?>"
                 class="w-full h-full <?= $is_affiliate ? 'object-contain bg-white p-6' : 'object-cover' ?> group-hover:scale-105 transition-transform duration-500"
                 alt="<?= htmlspecialchars($product_title) ?>"
                 <?= $lazyLoading ?>
                 onerror="this.src='data:image/svg+xml;utf8,<svg xmlns=\'http://www.w3.org/2000/svg\' width=\'80\' height=\'100\'><rect width=\'80\' height=\'100\' fill=\'%23f1f5f9\'/><text x=\'50%%27 y=\'50%%27 dominant-baseline=\'middle\' text-anchor=\'middle\' font-family=\'sans-serif\' font-size=\'10\' fill=\'%2394a3b8\'>RESİM YOK</text></svg>'">
        <?php endif; ?>

        <?php if ($is_affiliate): ?>
            <div class="absolute top-4 left-4 z-10">
                <span class="inline-flex items-center text-[11px] font-black px-3.5 py-2 rounded-xl shadow-xl ring-2 ring-white/95 border uppercase tracking-wider bg-amber-500 text-slate-950 border-amber-300">
                    Fırsat
                </span>
            </div>
        <?php else: ?>
            <div class="absolute top-4 left-4 z-10">
                <?= brochure_status_badge_html($selected_tab, $b['start_date'], $b['end_date'], $today) ?>
            </div>

            <?php if (brochure_has_ai_analysis($b)): ?>
                <div class="absolute z-10" style="top: 1rem; right: 1rem;">
                    <span class="text-white flex items-center justify-center material-symbols-outlined"
                          style="width: 2.5rem; height: 2.5rem; background: #6d28d9; border-radius: 1rem; border: 2px solid #fff; box-shadow: 0 16px 35px rgba(76, 29, 149, .32); font-size: 22px;"
                          title="Yapay zeka ürün analizi yapıldı">smart_toy</span>
                </div>
            <?php endif; ?>

            <div class="absolute bottom-3 left-3 bg-white border border-slate-100 rounded-xl p-1.5 shadow-md flex items-center justify-center w-11 h-11">
                <?php if (!empty($b['market_logo'])): ?>
                    <img src="uploads/markets/<?= htmlspecialchars($b['market_logo']) ?>" class="w-full h-full object-contain rounded" alt="" width="44" height="44">
                <?php else: ?>
                    <div class="text-[10px] font-bold text-slate-400">LOG</div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="p-5 flex-1 flex flex-col justify-between space-y-4">
        <?php if ($is_affiliate): ?>
            <div class="space-y-2.5">
                <h3 class="font-title text-base font-bold text-slate-900 line-clamp-2" title="<?= htmlspecialchars($product_title) ?>">
                    <?= htmlspecialchars($product_title) ?>
                </h3>
            </div>

            <div class="space-y-3 border-t border-slate-100 pt-4">
                <div class="flex items-center justify-between gap-3 text-xs">
                    <?php if ($product_rating !== ''): ?>
                        <span class="text-slate-600 font-semibold inline-flex items-center gap-1 min-w-0">
                            <span class="material-symbols-outlined text-sm text-amber-500">star</span>
                            <?= htmlspecialchars($product_rating) ?>
                            <?php if ($review_count !== ''): ?>
                                <span class="text-slate-400">(<?= htmlspecialchars($review_count) ?>)</span>
                            <?php endif; ?>
                        </span>
                    <?php else: ?>
                        <span aria-hidden="true"></span>
                    <?php endif; ?>
                    <span class="text-lg font-black text-slate-950 whitespace-nowrap"><?= $product_price !== '' ? htmlspecialchars($product_price) : 'Fiyat yok' ?></span>
                </div>

                <?php if ($product_url !== ''): ?>
                    <a href="<?= htmlspecialchars($product_url) ?>" target="_blank" rel="sponsored nofollow noopener"
                       class="w-full inline-flex items-center justify-center gap-2 rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 font-black px-4 py-3 transition">
                        <span class="material-symbols-outlined text-lg">open_in_new</span>
                        Hemen Al
                    </a>
                <?php else: ?>
                    <span class="w-full inline-flex items-center justify-center gap-2 rounded-xl bg-slate-100 text-slate-400 font-black px-4 py-3">
                        Link yok
                    </span>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div>
                <span class="text-xs font-bold text-red-600 tracking-wider uppercase mb-1.5 block"><?= htmlspecialchars($b['market_name']) ?></span>
                <h3 class="font-title text-base font-bold text-slate-800 line-clamp-2 hover:text-red-600 transition-colors" title="<?= htmlspecialchars($b['title']) ?>">
                    <?= htmlspecialchars($b['title']) ?>
                </h3>
            </div>

            <div class="flex justify-between items-center border-t border-slate-100 pt-4 text-xs">
                <span class="text-slate-600 font-semibold flex items-center gap-1">
                    <span class="material-symbols-outlined text-sm">date_range</span>
                    <?= date('d.m.Y', strtotime($b['start_date'])) ?> - <?= date('d.m.Y', strtotime($b['end_date'])) ?>
                </span>
                <span class="text-red-600 font-bold flex items-center gap-0.5 group-hover:translate-x-0.5 transition-transform">
                    İncele
                    <span class="material-symbols-outlined text-sm">chevron_right</span>
                </span>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($is_affiliate && count($affiliate_images) > 1 && empty($GLOBALS['mi_affiliate_slider_script_printed'])): ?>
    <?php $GLOBALS['mi_affiliate_slider_script_printed'] = true; ?>
    <script>
        (function () {
            if (window.miAffiliateSliderReady) return;
            window.miAffiliateSliderReady = true;

            function showSlide(slider, activeIndex) {
                var slides = slider.querySelectorAll('[data-affiliate-slide]');
                slider.dataset.active = String(activeIndex);
                slides.forEach(function (slide, index) {
                    slide.style.opacity = index === activeIndex ? '1' : '0';
                });
            }

            function tick() {
                document.querySelectorAll('[data-affiliate-slider]').forEach(function (slider) {
                    var slides = slider.querySelectorAll('[data-affiliate-slide]');
                    if (slides.length < 2) return;
                    var current = parseInt(slider.dataset.active || '0', 10);
                    showSlide(slider, (current + 1) % slides.length);
                });
            }

            function init() {
                document.querySelectorAll('[data-affiliate-slider]').forEach(function (slider) {
                    var current = parseInt(slider.dataset.active || '0', 10);
                    showSlide(slider, Number.isNaN(current) ? 0 : current);
                });
            }

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', init, { once: true });
            } else {
                init();
            }
            window.setInterval(tick, 5000);
        })();
    </script>
<?php endif; ?>
