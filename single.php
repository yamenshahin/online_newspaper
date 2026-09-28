<?php
/**
 * The template for displaying all single posts and Custom Post Types.
 */

get_header();

while (have_posts()):
    the_post();

    $current_post_type = get_post_type();

    // Fetch dynamic title from centralized function
    $cpt_labels = get_dynamic_cpt_labels($current_post_type);
    $dynamic_type_name = $cpt_labels['title'];

    // Fetch first term from the 'post_editor' custom taxonomy with fallback
    $editor_terms = get_the_terms(get_the_ID(), 'post_editor');
    $display_author = 'تفاعل السعودية';
    if (!empty($editor_terms) && !is_wp_error($editor_terms)) {
        $first_term = reset($editor_terms);
        if ($first_term && !empty($first_term->name)) {
            $display_author = $first_term->name;
        }
    }
    ?>
    <main id="primary" class="site-main bg-white pb-24">

        <!-- Hero Header Section -->
        <header class="py-16 md:py-24 bg-gray-50/50 border-b border-gray-100 mb-12">
            <!-- Using standard margin classes -->
            <div class="w-full px-margin-mobile lg:px-margin max-w-5xl mx-auto text-center">

                <!-- Dynamic Post Type Badge -->
                <span class="text-sm font-bold text-primary uppercase tracking-widest mb-4 block">
                    <?php echo esc_html($dynamic_type_name); ?>
                </span>

                <!-- Title matched to hero section sizing -->
                <h1 class="text-2xl md:text-3xl lg:text-4xl font-bold text-gray-900 tracking-tight mb-6 leading-[1.3]">
                    <?php the_title(); ?>
                </h1>

                <!-- Meta Data (Date & Editor Taxonomy) -->
                <div class="flex items-center justify-center gap-4 text-sm font-medium text-gray-500">
                    <time datetime="<?php echo get_the_date('c'); ?>"><?php echo get_the_date(); ?></time>
                    <span>&bull;</span>
                    <span><?php echo esc_html($display_author); ?></span>
                </div>

            </div>
        </header>

        <!-- Featured Image -->
        <?php if (has_post_thumbnail()): ?>
            <?php
            $wrapper_classes = 'max-w-5xl';
            $image_classes = 'w-full h-auto rounded-2xl';

            if ('infographic' === $current_post_type) {
                $wrapper_classes = 'max-w-2xl';
                $image_classes .= ' aspect-[9/16] object-contain bg-gray-50';
            }
            ?>
            <div
                class="<?php echo esc_attr($wrapper_classes); ?> mx-auto px-margin-mobile lg:px-margin mb-16 -mt-24 relative z-10">
                <div class="rounded-3xl overflow-hidden shadow-2xl shadow-gray-200/50 bg-white p-2">
                    <?php the_post_thumbnail('full', ['class' => $image_classes]); ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Main Content (Expanded to match site margins) -->
        <article class="w-full px-margin-mobile lg:px-margin">
            <div class="max-w-4xl mx-auto text-lg md:text-xl text-gray-700 leading-relaxed 
                        [&>p]:mb-6 
                        [&>h2]:text-3xl [&>h2]:font-bold [&>h2]:text-gray-900 [&>h2]:mt-12 [&>h2]:mb-6 [&>h2]:tracking-tight
                        [&>h3]:text-2xl [&>h3]:font-bold [&>h3]:text-on-background [&>h3]:mt-10 [&>h3]:mb-4
                        [&>ul]:list-disc [&>ul]:pl-6 [&>ul]:mb-6 [&>ul>li]:mb-2 [&>ul>li::marker]:text-gray-400
                        [&>ol]:list-decimal [&>ol]:pl-6 [&>ol]:mb-6 [&>ol>li]:mb-2
                        [&_a]:text-primary [&_a]:font-medium [&_a:hover]:text-primary/85 [&_a:hover]:underline 
                        [&>blockquote]:border-l-4 [&>blockquote]:border-primary [&>blockquote]:pl-6 [&>blockquote]:py-1 [&>blockquote]:italic [&>blockquote]:text-gray-600 [&>blockquote]:my-8 [&>blockquote]:bg-gray-50 [&>blockquote]:rounded-r-lg
                        [&_img]:w-full [&_img]:h-auto [&_img]:max-w-full [&_img]:object-cover [&_img]:rounded-2xl [&_img]:shadow-md [&_img]:my-8
                        [&>iframe]:w-full [&>iframe]:rounded-xl [&>iframe]:shadow-sm [&>iframe]:my-8">

                <?php the_content(); ?>

            </div>
        </article>

    </main>
    <?php
endwhile;

get_footer();