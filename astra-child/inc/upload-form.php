<?php
/** Login-protected frontend submission form for the lk_photo post type. */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_shortcode( 'lk_upload_form', 'lk_upload_form_shortcode' );
add_action( 'after_switch_theme', 'lk_create_upload_page' );

/** Resolve the real permalink, including plain-permalink installations. */
function lk_upload_page_url() {
    $page = get_page_by_path( 'dang-tac-pham' );
    return $page ? get_permalink( $page ) : home_url( '/dang-tac-pham/' );
}

/** Create a missing page without altering an existing user's page. */
function lk_create_upload_page() {
    if ( get_page_by_path( 'dang-tac-pham' ) ) {
        return true;
    }

    $page_id = wp_insert_post(
        array(
            'post_type'    => 'page',
            'post_status'  => 'publish',
            'post_title'   => 'Đăng tác phẩm',
            'post_name'    => 'dang-tac-pham',
            'post_content' => '[lk_upload_form]',
        ),
        true
    );
    return ! is_wp_error( $page_id ) && $page_id > 0;
}

/** Resolve a validated choice; create a new term only after the photo is saved. */
function lk_submission_topic( $choice, $new_name, $create = false ) {
    if ( 'other' === $choice ) {
        if ( '' === $new_name || mb_strlen( $new_name ) > 80 ) {
            return new WP_Error( 'topic_name', 'Hãy nhập tên chủ đề mới, tối đa 80 ký tự.' );
        }
        $existing = term_exists( $new_name, 'lk_topic' );
        if ( $existing ) { return (int) ( is_array( $existing ) ? $existing['term_id'] : $existing ); }
        if ( ! $create ) { return 0; }
        $term = wp_insert_term( $new_name, 'lk_topic' );
        if ( is_wp_error( $term ) ) {
            return 'term_exists' === $term->get_error_code() ? (int) $term->get_error_data() : $term;
        }
        return (int) $term['term_id'];
    }
    if ( '' === $choice || '0' === $choice ) {
        $term = get_term_by( 'slug', 'tu-do', 'lk_topic' ) ?: get_term_by( 'name', 'Tự do', 'lk_topic' );
    } else {
        $term = ctype_digit( $choice ) ? get_term( (int) $choice, 'lk_topic' ) : null;
    }
    return $term && ! is_wp_error( $term ) ? (int) $term->term_id : new WP_Error( 'topic_invalid', 'Chủ đề không hợp lệ. Hãy chọn lại chủ đề trong danh sách.' );
}

/** PHP ini quantities use binary K/M/G; zero and negative values mean no limit. */
function lk_ini_size_bytes( $value ) {
    $value = trim( (string) $value );
    if ( ! preg_match( '/^(-?\d+)\s*([KMG]?)$/i', $value, $matches ) ) {
        return null; // Unknown configuration must not be presented as a verified limit.
    }
    $bytes = (float) $matches[1];
    $unit = strtoupper( $matches[2] );
    $power = array( '' => 0, 'K' => 1, 'M' => 2, 'G' => 3 );
    return $bytes > 0 ? $bytes * pow( 1024, $power[ $unit ] ) : 0;
}

/** Process submissions before the template sends headers. */
add_action( 'template_redirect', 'lk_handle_upload' );
function lk_handle_upload() {
    $page = get_queried_object();
    if ( ! is_singular() || ! ( $page instanceof WP_Post ) || ! has_shortcode( $page->post_content, 'lk_upload_form' ) ) {
        return;
    }
    nocache_headers();
    if ( ! is_user_logged_in() || ! current_user_can( 'upload_files' ) || ! current_user_can( 'edit_lk_photos' ) ) {
        return;
    }
    $page_url = get_permalink() ?: lk_upload_page_url();
    $notice = '';
    $method = isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '';

    // PHP discards BOTH POST and FILES before WordPress runs when this is exceeded.
    $post_limit = lk_ini_size_bytes( ini_get( 'post_max_size' ) );
    $content_length = isset( $_SERVER['CONTENT_LENGTH'] ) ? trim( (string) $_SERVER['CONTENT_LENGTH'] ) : '';
    if ( 'POST' === $method && $post_limit > 0 && ctype_digit( $content_length ) && (float) $content_length > $post_limit ) {
        status_header( 413 );
        $GLOBALS['lk_upload_error'] = sprintf(
            'Tổng dữ liệu gửi vượt giới hạn máy chủ (%s). PHP đã hủy dữ liệu biểu mẫu; hãy chọn ảnh nhỏ hơn và nhập lại nội dung. Giới hạn riêng của ảnh là 12 MB.',
            size_format( $post_limit )
        );
        return;
    }

    if ( 'POST' === $method && isset( $_POST['lk_upload_submit'] ) ) {
        $nonce = isset( $_POST['lk_upload_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['lk_upload_nonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, 'lk_upload_photo' ) ) {
            $notice = 'Phiên gửi biểu mẫu đã hết hạn. Hãy tải lại trang và thử lại.';
        } else {
            $title = isset( $_POST['lk_photo_title'] ) ? sanitize_text_field( wp_unslash( $_POST['lk_photo_title'] ) ) : '';
            $description = isset( $_POST['lk_photo_description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['lk_photo_description'] ) ) : '';
            $topic_choice = isset( $_POST['lk_topic'] ) ? sanitize_text_field( wp_unslash( $_POST['lk_topic'] ) ) : '0';
            $new_topic = isset( $_POST['lk_new_topic'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['lk_new_topic'] ) ) ) : '';
            $topic_id = lk_submission_topic( $topic_choice, $new_topic );
            $file = isset( $_FILES['lk_photo_file'] ) && is_array( $_FILES['lk_photo_file'] ) ? $_FILES['lk_photo_file'] : null;
            $file_error = $file && isset( $file['error'] ) ? (int) $file['error'] : UPLOAD_ERR_NO_FILE;
            $file_size = $file && isset( $file['tmp_name'] ) && is_string( $file['tmp_name'] ) && is_uploaded_file( $file['tmp_name'] ) ? filesize( $file['tmp_name'] ) : 0;
            $max_size = 12 * MB_IN_BYTES;

            if ( '' === $title ) {
                $notice = 'Hãy nhập tên tác phẩm.';
            } elseif ( is_wp_error( $topic_id ) ) {
                $notice = $topic_id->get_error_message();
            } elseif ( ! $file || UPLOAD_ERR_NO_FILE === $file_error ) {
                $notice = 'Hãy chọn một tệp ảnh.';
            } elseif ( UPLOAD_ERR_INI_SIZE === $file_error ) {
                $notice = 'Ảnh vượt giới hạn tệp của máy chủ (upload_max_filesize). Hãy chọn ảnh nhỏ hơn; giới hạn riêng của biểu mẫu là 12 MB.';
            } elseif ( UPLOAD_ERR_OK !== $file_error ) {
                $notice = 'Không tải được tệp. Hãy thử lại hoặc chọn ảnh nhỏ hơn.';
            } elseif ( ! $file_size || ! isset( $file['name'] ) || ! is_string( $file['name'] ) ) {
                $notice = 'Tệp tải lên không hợp lệ.';
            } elseif ( $file_size > $max_size ) {
                $notice = 'Ảnh vượt giới hạn 12 MB của biểu mẫu.';
            } elseif ( lk_ini_size_bytes( ini_get( 'upload_max_filesize' ) ) > 0 && $file_size > lk_ini_size_bytes( ini_get( 'upload_max_filesize' ) ) ) {
                $notice = 'Ảnh vượt giới hạn tệp của máy chủ (upload_max_filesize).';
            } else {
                $allowed_mimes = array(
                    'jpg|jpeg' => 'image/jpeg',
                    'png'      => 'image/png',
                    'webp'     => 'image/webp',
                );
                $checked_file = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'], $allowed_mimes );

                if ( empty( $checked_file['type'] ) || ! in_array( $checked_file['type'], $allowed_mimes, true ) || wp_get_image_mime( $file['tmp_name'] ) !== $checked_file['type'] ) {
                    $notice = 'Chỉ chấp nhận ảnh JPG, PNG hoặc WebP.';
                } else {
                    require_once ABSPATH . 'wp-admin/includes/file.php';
                    require_once ABSPATH . 'wp-admin/includes/media.php';
                    require_once ABSPATH . 'wp-admin/includes/image.php';

                    $attachment_id = media_handle_upload(
                        'lk_photo_file',
                        0,
                        array(),
                        array(
                            'test_form' => false,
                            'mimes'     => $allowed_mimes,
                        )
                    );

                    if ( is_wp_error( $attachment_id ) ) {
                        $notice = 'WordPress không xử lý được ảnh. Hãy thử ảnh khác.';
                    } else {
                        $photo_id = wp_insert_post(
                            array(
                                'post_type'    => 'lk_photo',
                                'post_status'  => 'pending',
                                'post_title'   => $title,
                                'post_content' => $description,
                                'post_author'  => get_current_user_id(),
                            ),
                            true
                        );

                        if ( is_wp_error( $photo_id ) || ! $photo_id ) {
                            wp_delete_attachment( $attachment_id, true );
                            $notice = 'Không lưu được tác phẩm. Hãy thử lại.';
                        } else {
                            if ( ! set_post_thumbnail( $photo_id, $attachment_id ) ) {
                                wp_delete_post( $photo_id, true );
                                wp_delete_attachment( $attachment_id, true );
                                $GLOBALS['lk_upload_error'] = 'Không gắn được ảnh vào tác phẩm. Hãy thử ảnh khác.';
                                return;
                            }
                            update_post_meta( $attachment_id, '_wp_attachment_image_alt', $title );
                            wp_update_post(
                                array(
                                    'ID'          => $attachment_id,
                                    'post_parent' => $photo_id,
                                )
                            );

                            $topic_id = lk_submission_topic( $topic_choice, $new_topic, true );
                            $assigned = is_wp_error( $topic_id ) ? $topic_id : wp_set_object_terms( $photo_id, array( $topic_id ), 'lk_topic' );
                            if ( is_wp_error( $assigned ) ) {
                                wp_delete_post( $photo_id, true );
                                wp_delete_attachment( $attachment_id, true );
                                $GLOBALS['lk_upload_error'] = 'Không lưu được chủ đề. Hãy thử lại.';
                                return;
                            }

                            $redirect_url = $page_url ? $page_url : home_url( '/dang-tac-pham/' );
                            wp_safe_redirect( add_query_arg( array(
                                'lk_upload' => 'pending',
                                'lk_upload_id' => $photo_id,
                                'lk_receipt' => wp_create_nonce( 'lk_upload_success_' . $photo_id ),
                            ), $redirect_url ), 303 );
                            exit;
                        }
                    }
                }
            }
        }
    }

    $GLOBALS['lk_upload_error'] = $notice;
}

/** Render the login-protected submission form. */
function lk_upload_form_shortcode() {
    $page_url = get_permalink() ?: lk_upload_page_url();
    if ( ! is_user_logged_in() ) {
        return '<section class="lk-upload"><p>Đăng nhập bằng tài khoản tác giả để gửi tác phẩm.</p><p><a href="' . esc_url( wp_login_url( $page_url ) ) . '">Đăng nhập</a></p></section>';
    }
    if ( ! current_user_can( 'upload_files' ) || ! current_user_can( 'edit_lk_photos' ) ) {
        return '<section class="lk-upload"><p>Tài khoản hiện tại chưa được cấp quyền tải ảnh lên.</p></section>';
    }
    $notice = isset( $GLOBALS['lk_upload_error'] ) ? $GLOBALS['lk_upload_error'] : '';
    $notice_class = 'error';
    $photo_id = isset( $_GET['lk_upload_id'] ) ? absint( $_GET['lk_upload_id'] ) : 0;
    $receipt = isset( $_GET['lk_receipt'] ) ? sanitize_text_field( wp_unslash( $_GET['lk_receipt'] ) ) : '';
    $photo = $photo_id ? get_post( $photo_id ) : null;
    if ( 'GET' === ( $_SERVER['REQUEST_METHOD'] ?? '' ) &&
        isset( $_GET['lk_upload'] ) && 'pending' === $_GET['lk_upload'] &&
        wp_verify_nonce( $receipt, 'lk_upload_success_' . $photo_id ) &&
        $photo && 'lk_photo' === $photo->post_type &&
        (int) $photo->post_author === get_current_user_id() &&
        'pending' === $photo->post_status && get_post_thumbnail_id( $photo_id ) ) {
        $notice = 'Tác phẩm đã được gửi và đang chờ quản trị viên duyệt.';
        $notice_class = 'success';
    }

    $topics = get_terms(
        array(
            'taxonomy'   => 'lk_topic',
            'hide_empty' => false,
        )
    );

    $selected_topic = isset( $_POST['lk_topic'] ) ? sanitize_text_field( wp_unslash( $_POST['lk_topic'] ) ) : '0';
    $new_topic = isset( $_POST['lk_new_topic'] ) ? sanitize_text_field( wp_unslash( $_POST['lk_new_topic'] ) ) : '';
    ob_start();
    ?>
    <section class="lk-upload" aria-labelledby="lk-upload-title">
        <h1 id="lk-upload-title">Gửi tác phẩm</h1>
        <?php if ( $notice ) : ?>
            <p class="lk-upload-message lk-upload-message--<?php echo esc_attr( $notice_class ); ?>" role="status"><?php echo esc_html( $notice ); ?></p>
        <?php endif; ?>
        <form method="post" action="<?php echo esc_url( $page_url ); ?>" enctype="multipart/form-data">
            <?php echo wp_nonce_field( 'lk_upload_photo', 'lk_upload_nonce', true, false ); ?>
            <p>
                <label for="lk-photo-title">Tên tác phẩm</label>
                <input id="lk-photo-title" name="lk_photo_title" type="text" maxlength="200" value="<?php echo esc_attr( isset( $_POST['lk_photo_title'] ) ? sanitize_text_field( wp_unslash( $_POST['lk_photo_title'] ) ) : '' ); ?>" required>
            </p>
            <p>
                <label for="lk-photo-description">Mô tả</label>
                <textarea id="lk-photo-description" name="lk_photo_description" rows="4"><?php echo esc_textarea( isset( $_POST['lk_photo_description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['lk_photo_description'] ) ) : '' ); ?></textarea>
            </p>
            <p>
                <label for="lk-photo-topic">Chủ đề / album</label>
                <select id="lk-photo-topic" name="lk_topic" aria-controls="lk-new-topic-field">
                    <option value="0" <?php selected( $selected_topic, '0' ); ?>>Tự do (không chọn chủ đề)</option>
                    <?php if ( ! is_wp_error( $topics ) ) : foreach ( $topics as $topic ) : ?>
                        <?php if ( 'tu-do' === $topic->slug || 'Tự do' === $topic->name ) { continue; } ?>
                        <option value="<?php echo esc_attr( $topic->term_id ); ?>" <?php selected( $selected_topic, (string) $topic->term_id ); ?>><?php echo esc_html( $topic->name ); ?></option>
                    <?php endforeach; endif; ?>
                    <option value="other" <?php selected( $selected_topic, 'other' ); ?>>Khác — tạo chủ đề mới</option>
                </select>
            </p>
            <p id="lk-new-topic-field">
                <label for="lk-new-topic">Tên chủ đề mới (khi chọn Khác)</label>
                <input id="lk-new-topic" name="lk_new_topic" type="text" maxlength="80" value="<?php echo esc_attr( $new_topic ); ?>" autocomplete="off">
            </p>
            <p>
                <label for="lk-photo-file">Ảnh (JPG, PNG hoặc WebP; tối đa 12 MB)</label>
                <input id="lk-photo-file" name="lk_photo_file" type="file" accept="image/jpeg,image/png,image/webp" required>
            </p>
            <p>Ảnh gửi lên sẽ được quản trị viên duyệt trước khi xuất hiện trong thư viện.</p>
            <p><button type="submit" name="lk_upload_submit" value="1">Gửi tác phẩm</button></p>
        </form>
    </section>
    <?php

    return ob_get_clean();
}
