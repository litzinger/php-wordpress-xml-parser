<?php

namespace Litzinger\PHPWordpressXMLParser\Parsers;

use DOMDocument;
use Exception;

class SimpleXML {
    private array $postCollection;
    private array $postMeta;
    private array $customFieldNames;
    private array $customFields;
    private array $postTypes;


    public function parse($file)
    {
        $authors    = array();
        $posts      = array();
        $categories = array();
        $tags       = array();
        $terms      = array();
        $postTypes = [];

        $internal_errors = libxml_use_internal_errors( true );

        $dom       = new DOMDocument();
        $old_value = null;
        if ( function_exists( 'libxml_disable_entity_loader' ) && PHP_VERSION_ID < 80000 ) {
            $old_value = libxml_disable_entity_loader( true );
        }
        $success = $dom->loadXML( file_get_contents( $file ) );
        if ( ! is_null( $old_value ) ) {
            libxml_disable_entity_loader( $old_value );
        }

        if ( ! $success || isset( $dom->doctype ) ) {
            throw new Exception( 'There was an error when reading this WXR file' );
        }

        $xml = simplexml_import_dom( $dom );
        unset( $dom );

        // halt if loading produces an error
        if ( ! $xml ) {
            throw new Exception( 'There was an error when reading this WXR file' );
        }

        $wxr_version = $xml->xpath( '/rss/channel/wp:wxr_version' );
        if ( ! $wxr_version ) {
            throw new Exception( 'This does not appear to be a WXR file, missing/invalid WXR version number' );
        }

        $wxr_version = (string) trim( $wxr_version[0] );
        // confirm that we are dealing with the correct file format
        if ( ! preg_match( '/^\d+\.\d+$/', $wxr_version ) ) {
            throw new Exception( 'This does not appear to be a WXR file, missing/invalid WXR version number' );
        }

        $base_url = $xml->xpath( '/rss/channel/wp:base_site_url' );
        $base_url = (string) trim( isset( $base_url[0] ) ? $base_url[0] : '' );

        $base_blog_url = $xml->xpath( '/rss/channel/wp:base_blog_url' );
        if ( $base_blog_url ) {
            $base_blog_url = (string) trim( $base_blog_url[0] );
        } else {
            $base_blog_url = $base_url;
        }

        $namespaces = $xml->getDocNamespaces();
        if ( ! isset( $namespaces['wp'] ) ) {
            $namespaces['wp'] = 'http://wordpress.org/export/1.1/';
        }
        if ( ! isset( $namespaces['excerpt'] ) ) {
            $namespaces['excerpt'] = 'http://wordpress.org/export/1.1/excerpt/';
        }

        // grab authors
        foreach ( $xml->xpath( '/rss/channel/wp:author' ) as $author_arr ) {
            $a                 = $author_arr->children( $namespaces['wp'] );
            $login             = (string) $a->author_login;
            $authors[ $login ] = array(
                'author_id'           => (int) $a->author_id,
                'author_login'        => $login,
                'author_email'        => (string) $a->author_email,
                'author_display_name' => (string) $a->author_display_name,
                'author_first_name'   => (string) $a->author_first_name,
                'author_last_name'    => (string) $a->author_last_name,
            );
        }

        // grab cats, tags and terms
        foreach ( $xml->xpath( '/rss/channel/wp:category' ) as $term_arr ) {
            $t        = $term_arr->children( $namespaces['wp'] );
            $category = array(
                'term_id'              => (int) $t->term_id,
                'category_nicename'    => (string) $t->category_nicename,
                'category_parent'      => (string) $t->category_parent,
                'cat_name'             => (string) $t->cat_name,
                'category_description' => (string) $t->category_description,
            );

            foreach ( $t->termmeta as $meta ) {
                $category['termmeta'][] = array(
                    'key'   => (string) $meta->meta_key,
                    'value' => (string) $meta->meta_value,
                );
            }

            $categories[] = $category;
        }

        foreach ( $xml->xpath( '/rss/channel/wp:tag' ) as $term_arr ) {
            $t   = $term_arr->children( $namespaces['wp'] );
            $tag = array(
                'term_id'         => (int) $t->term_id,
                'tag_slug'        => (string) $t->tag_slug,
                'tag_name'        => (string) $t->tag_name,
                'tag_description' => (string) $t->tag_description,
            );

            foreach ( $t->termmeta as $meta ) {
                $tag['termmeta'][] = array(
                    'key'   => (string) $meta->meta_key,
                    'value' => (string) $meta->meta_value,
                );
            }

            $tags[] = $tag;
        }

        foreach ( $xml->xpath( '/rss/channel/wp:term' ) as $term_arr ) {
            $t    = $term_arr->children( $namespaces['wp'] );
            $term = array(
                'term_id'          => (int) $t->term_id,
                'term_taxonomy'    => (string) $t->term_taxonomy,
                'slug'             => (string) $t->term_slug,
                'term_parent'      => (string) $t->term_parent,
                'term_name'        => (string) $t->term_name,
                'term_description' => (string) $t->term_description,
            );

            foreach ( $t->termmeta as $meta ) {
                $term['termmeta'][] = array(
                    'key'   => (string) $meta->meta_key,
                    'value' => (string) $meta->meta_value,
                );
            }

            $terms[] = $term;
        }

        // grab posts
        foreach ($xml->channel->item as $item) {
            $post = array(
                'post_title' => (string) $item->title,
                'guid'       => (string) $item->guid,
            );

            $dc                  = $item->children( 'http://purl.org/dc/elements/1.1/' );
            $post['post_author'] = (string) $dc->creator;

            $content              = $item->children( 'http://purl.org/rss/1.0/modules/content/' );
            $excerpt              = $item->children( $namespaces['excerpt'] );
            $post['post_content'] = (string) $content->encoded;
            $post['post_excerpt'] = (string) $excerpt->encoded;

            $wp                     = $item->children( $namespaces['wp'] );
            $post['post_id']        = (int) $wp->post_id;
            $post['post_date']      = (string) $wp->post_date;
            $post['post_date_gmt']  = (string) $wp->post_date_gmt;
            $post['comment_status'] = (string) $wp->comment_status;
            $post['ping_status']    = (string) $wp->ping_status;
            $post['post_name']      = (string) $wp->post_name;
            $post['status']         = (string) $wp->status;
            $post['post_parent']    = (int) $wp->post_parent;
            $post['menu_order']     = (int) $wp->menu_order;
            $post['post_type']      = (string) $wp->post_type;
            $post['post_password']  = (string) $wp->post_password;
            $post['is_sticky']      = (int) $wp->is_sticky;

            $this->postTypes[] = $post['post_type'];

            if ( isset( $wp->attachment_url ) ) {
                $post['attachment_url'] = (string) $wp->attachment_url;
            }

            foreach ( $item->category as $c ) {
                $att = $c->attributes();
                if ( isset( $att['nicename'] ) ) {
                    $post['terms'][] = array(
                        'name'   => (string) $c,
                        'slug'   => (string) $att['nicename'],
                        'domain' => (string) $att['domain'],
                    );
                }
            }

            foreach ( $wp->comment as $comment ) {
                $meta = array();
                if ( isset( $comment->commentmeta ) ) {
                    foreach ( $comment->commentmeta as $m ) {
                        $meta[] = array(
                            'key'   => (string) $m->meta_key,
                            'value' => (string) $m->meta_value,
                        );
                    }
                }

                $post['comments'][] = array(
                    'comment_id'           => (int) $comment->comment_id,
                    'comment_author'       => (string) $comment->comment_author,
                    'comment_author_email' => (string) $comment->comment_author_email,
                    'comment_author_IP'    => (string) $comment->comment_author_IP,
                    'comment_author_url'   => (string) $comment->comment_author_url,
                    'comment_date'         => (string) $comment->comment_date,
                    'comment_date_gmt'     => (string) $comment->comment_date_gmt,
                    'comment_content'      => (string) $comment->comment_content,
                    'comment_approved'     => (string) $comment->comment_approved,
                    'comment_type'         => (string) $comment->comment_type,
                    'comment_parent'       => (string) $comment->comment_parent,
                    'comment_user_id'      => (int) $comment->comment_user_id,
                    'commentmeta'          => $meta,
                );
            }

            foreach ($wp->postmeta as $meta) {
                $this->postMeta[(int) $wp->post_id][] = [
                    'key'   => (string) $meta->meta_key,
                    'value' => (string) $meta->meta_value,
                ];
            }

            if ((string) $wp->post_type === 'acf-field') {
                $content = $item->children('http://purl.org/rss/1.0/modules/content/');
                $excerpt = $item->children($namespaces['excerpt']);

                $settings = unserialize((string) $content->encoded);
                $type = $settings['type'];

                $fieldId = (string) $wp->post_name;

                $this->customFields[$fieldId] = [
                    'type' => $type,
                    'name' => (string) $excerpt->encoded,
                    'parent' => (int) $wp->post_parent,
                    'post_id' => (int) $wp->post_id,
                ];

                $this->customFieldNames[] = (string) $excerpt->encoded;
            }

            $this->postCollection[$post['post_id']] = $post;
        }

        foreach ($this->postCollection as &$post) {
            $allMeta = $this->findPostMetaById($post['post_id']);

            foreach ($allMeta as $meta) {
                $key = (string) $meta['key'];
                $val = (string) $meta['value'];

                $post['postmeta'][] = array(
                    'key'   => $key,
                    'value' => $val,
                );

                if (array_key_exists($val, $this->customFields)) {
                    if (
                        substr($key, 0, 1) === '_' &&
                        substr($val, 0, 6) === 'field_'
                    ) {
                        $cleanedKey = substr($key, 0, 1);
                        $fieldId = $val;

                        $relatedPost = current($this->findPostByCustomFieldName($fieldId));
                        $f = $this->findRelatedPostMetaValueByKey((int) $post['post_id'], $fieldId);

                        if ($f['parentName']) {
                            $post['custom_fields'][$f['parentName']][] = $f['fieldValues'];
                        } else {
                            $post['custom_fields'][$f['fieldName']] = $f['fieldValues'];
                        }
                    }
                }
            }
        }


        return array(
            'authors'       => $authors,
            'post_types'    => array_unique($postTypes),
            'posts'         => $this->postCollection,
            //'categories'    => $categories,
            //'tags'          => $tags,
            //'terms'         => $terms,
            //'base_url'      => $base_url,
            //'base_blog_url' => $base_blog_url,
            //'version'       => $wxr_version,
            'custom_fields' => $this->customFields,
            'custom_fields_names' => $this->customFieldNames,
        );
    }

    private function findPostById(int $id): array
    {
        return array_reduce($this->postCollection, static function ($post) use ($id) {
            return $post['post_id'] === $id;
        });
    }

    private function findPostByCustomFieldName(string $name): array
    {
        return array_filter($this->postCollection, function ($post) use ($name) {
            return $post['post_name'] === $name;
        });
    }

    private function findPostMetaById(int $id): array
    {
        return $this->postMeta[$id] ?? [];
    }

    private function findRelatedPostMetaValueByKey(int $id, string $fieldId): array
    {
        $metaCollection = $this->findPostMetaById($id);

        $filteredByValue = array_filter($metaCollection, function ($meta) use ($fieldId) {
            return $meta['value'] === $fieldId;
        });

        $keys = array_column($filteredByValue, 'key');

        $keysCleaned = array_map(function ($key) {
            return substr($key, 0, 1) === '_' ? substr($key, 1) : $key;
        }, $keys);

        $filteredByKey = array_filter($metaCollection, function ($meta) use ($keysCleaned) {
            return in_array($meta['key'], $keysCleaned);
        });

        $fields = array_values(array_map(function ($meta) use ($fieldId) {
            $meta['fieldId'] = $fieldId;

            return $meta;
        }, $filteredByKey));

        $customFieldName = $this->customFields[$fieldId]['name'];

        $parent = $this->findParentField($fieldId);
        $parentName = '';

        if (count($parent) === 1) {
            $parent = array_values($parent);
            $parentName = $parent[0]['name'] ?? '';
        }

        // @todo move this to a separate function and handle special cases
        foreach ($fields as &$field) {
            if (!ctype_digit($field['value'])) {
                continue;
            }

            $int = (int) $field['value'];

            if (isset($this->postCollection[$int]) && $this->postCollection[$int]['post_type'] === 'attachment') {
                $field['value'] = $this->postCollection[$int]['attachment_url'];
            }
        }

        return [
            'fieldName' => $customFieldName,
            'fieldValues' => $fields,
            'parentName' => $parentName,
        ];
    }

    public function findParentField(string $fieldId): array
    {
        $currentField = $this->customFields[$fieldId];

        return array_filter($this->customFields, function ($field) use ($currentField) {
            return $field['post_id'] === $currentField['parent'];
        });
    }

}
