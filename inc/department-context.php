<?php
/**
 * Department term for chrome (header/footer): current context or null.
 */
function get_context_department(): ?WP_Term
{
    if (is_admin()) {
        return null;
    }

    if (is_tax('department')) {
        $term = get_queried_object();
        return $term instanceof WP_Term ? $term : null;
    }

    if (is_singular()) {
        $terms = get_the_terms(get_the_ID(), 'department');
        if (!empty($terms) && !is_wp_error($terms)) {
            return $terms[0];
        }
    }

    return null;
}

/**
 * Canonical fallback department (عن السعودية).
 */
function get_fallback_department(): ?WP_Term
{
    $term = get_term_by('slug', 'about-saudia', 'department');
    return ($term instanceof WP_Term) ? $term : null;
}

/**
 * ACF value from a department term, with per-field fallback to about-saudia.
 *
 * @param string      $field   ACF field name.
 * @param WP_Term|null $term   Primary term (context). Null = use fallback only.
 * @return mixed
 */
function get_department_field_with_fallback(string $field, ?WP_Term $term = null)
{
    $fallback = get_fallback_department();

    if ($term instanceof WP_Term) {
        $value = get_field($field, $term);
        if (!is_department_field_empty($value)) {
            return $value;
        }
    }

    if ($fallback instanceof WP_Term) {
        return get_field($field, $fallback);
    }

    return null;
}

/**
 * Empty check for ACF values (image arrays, repeaters, strings).
 */
function is_department_field_empty($value): bool
{
    if ($value === null || $value === false || $value === '') {
        return true;
    }
    if (is_array($value) && empty($value)) {
        return true;
    }
    return false;
}
