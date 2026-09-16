<?php
/**
 * The template for displaying all single posts and Custom Post Types.
 */

get_header();

while (have_posts()):
    the_post();

    $current_post_type = get_post_type();
    ?>
    <main id="primary" class="site-main bg-white pb-24">

        <!-- Hero Header Section -->
        <header class="py-16 md:py-24 bg-gray-50/50 border-b border-gray-100 mb-12">
            <div class="max-w-4xl mx-auto px-6 text-center">

                <!-- Dynamic Post Type Badge -->
                <span class="text-xs font-bold text-primary uppercase tracking-widest mb-4 block">
                    <?php
                    if ('post' === $current_post_type) {
                        echo esc_html__('Latest News', 'hello-elementor-child');
                    } else {
                        $post_type_obj = get_post_type_object($current_post_type);
                        echo esc_html($post_type_obj->labels->singular_name);
                    }
                    ?>
                </span>

                <!-- Title -->
                <h1 class="text-4xl md:text-5xl lg:text-6xl font-bold text-gray-900 tracking-tight mb-6 leading-tight">
                    <?php the_title(); ?>
                </h1>

                <!-- Meta Data (Date & Author) -->
                <div class="flex items-center justify-center gap-4 text-sm font-medium text-gray-500">
                    <time datetime="<?php echo get_the_date('c'); ?>"><?php echo get_the_date(); ?></time>
                    <span>&bull;</span>
                    <span><?php echo get_the_author(); ?></span>
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
            <div class="<?php echo esc_attr($wrapper_classes); ?> mx-auto px-6 mb-16 -mt-24 relative z-10">
                <div class="rounded-3xl overflow-hidden shadow-2xl shadow-gray-200/50 bg-white p-2">
                    <?php the_post_thumbnail('full', ['class' => $image_classes]); ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Main Content -->
        <article class="max-w-3xl mx-auto px-6">

            <div class="text-lg md:text-xl text-gray-700 leading-relaxed 
                        [&>p]:mb-6 
                        [&>h2]:text-3xl [&>h2]:font-bold [&>h2]:text-gray-900 [&>h2]:mt-12 [&>h2]:mb-6 [&>h2]:tracking-tight
                        [&>h3]:text-2xl [&>h3]:font-bold [&>h3]:text-gray-900 [&>h3]:mt-10 [&>h3]:mb-4
                        [&>ul]:list-disc [&>ul]:pl-6 [&>ul]:mb-6 [&>ul>li]:mb-2 [&>ul>li::marker]:text-gray-400
                        [&>ol]:list-decimal [&>ol]:pl-6 [&>ol]:mb-6 [&>ol>li]:mb-2
                        [&>a]:text-primary [&>a]:font-medium [&>a:hover]:text-primary/85 [&>a:hover]:underline 
                        [&>blockquote]:border-l-4 [&>blockquote]:border-primary [&>blockquote]:pl-6 [&>blockquote]:py-1 [&>blockquote]:italic [&>blockquote]:text-gray-600 [&>blockquote]:my-8 [&>blockquote]:bg-gray-50 [&>blockquote]:rounded-r-lg
                        [&>img]:rounded-2xl [&>img]:shadow-md [&>img]:my-8 [&>img]:w-full [&>img]:h-auto
                        [&>iframe]:w-full [&>iframe]:rounded-xl [&>iframe]:shadow-sm [&>iframe]:my-8">

                <?php the_content(); ?>

            </div>

        </article>

    </main>
    <?php
endwhile;

get_footer();