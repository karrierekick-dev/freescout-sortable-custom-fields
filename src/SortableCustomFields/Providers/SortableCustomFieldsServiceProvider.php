<?php

namespace Modules\SortableCustomFields\Providers;

use Modules\CustomFields\Entities\CustomField;
use Illuminate\Support\ServiceProvider;

define('CF_SORTABLE_MODULE', 'sortablecustomfields');

class SortableCustomFieldsServiceProvider extends ServiceProvider
{
    /**
     * Boot the application events.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerConfig();
        $this->hooks();
    }

    public static function createSlug($str, $delimiter = '_')
    {
        $slug = \Str::slug($str, $delimiter, 'en');
        return $slug;
    }

    protected function isSearchPage()
    {
        return \Route::is('conversations.search') || request()->is('search') || request()->is('search/*');
    }

    /**
     * Initial search page or AJAX pagination/sorting of search results.
     * Sorting uses conversations.ajax and sends filters as filter[f], not f.
     */
    protected function isSearchContext()
    {
        if ($this->isSearchPage()) {
            return true;
        }

        $filter = request()->input('filter');
        return is_array($filter) && array_key_exists('q', $filter);
    }

    /**
     * Search filters from the initial GET (?f[...]) or AJAX sorting/pagination (filter[f]).
     */
    protected function getRequestSearchFilters()
    {
        $filters = request()->input('f');
        if (is_array($filters)) {
            return $filters;
        }

        $nested = request()->input('filter.f');
        if (is_array($nested)) {
            return $nested;
        }

        return [];
    }

    /**
     * Custom field columns are only consistent in a single-mailbox list.
     * Search / multi-mailbox views mix fields and break the table layout (#1).
     */
    protected function getMailboxIdForList()
    {
        if ($this->isSearchContext()) {
            return 0;
        }

        if (!empty(request()->mailbox_id)) {
            return (int) request()->mailbox_id;
        }

        if (\Route::is('mailboxes.view') || \Route::is('mailboxes.view.folder')) {
            return (int) request()->route('id');
        }

        $mailbox = \Helper::getGlobalEntity('mailbox');
        if ($mailbox && !empty($mailbox->id)) {
            return (int) $mailbox->id;
        }

        return 0;
    }

    /**
     * On search, only show columns for custom fields that are active as filters
     * (e.g. #Dringlichkeit), so the table stays aligned across mailboxes.
     */
    protected function getSearchFilterCustomFields()
    {
        $filters = $this->getRequestSearchFilters();
        if (!count($filters)) {
            return collect();
        }

        $search_fields = CustomField::getSearchCustomFields();
        if (!$search_fields || !count($search_fields)) {
            return collect();
        }

        $active = collect();
        $seen_names = [];

        foreach ($search_fields as $custom_field) {
            // Search filter keys look like "#Dringlichkeit".
            // Keep the column even when the filter value is empty/null (""),
            // as long as the filter itself is present in the request.
            if (!array_key_exists($custom_field->name, $filters)) {
                continue;
            }

            $display = clone $custom_field;
            $display_name = ltrim($custom_field->name, '#');
            // Remove mailbox suffix when names collide: "Name {123}"
            $display_name = preg_replace('/\s*\{\d+\}$/', '', $display_name);
            $display->name = $display_name;

            if (isset($seen_names[$display_name])) {
                continue;
            }
            $seen_names[$display_name] = true;
            $active->push($display);
        }

        return $active->values();
    }

    protected function getListCustomFields()
    {
        if ($this->isSearchContext()) {
            return $this->getSearchFilterCustomFields();
        }

        $mailbox_id = $this->getMailboxIdForList();
        if (!$mailbox_id) {
            return collect();
        }

        return CustomField::where('mailbox_id', $mailbox_id)
            // groupBy('name') does not work in PostgreSQL.
            ->distinct('name')
            ->get()
            ->filter(function ($custom_field) {
                return (bool) $custom_field->show_in_list;
            })
            ->values();
    }

    protected function applyCustomFieldSorting($query_conversations)
    {
        if (empty($_REQUEST['sorting']['sort_by']) || strpos($_REQUEST['sorting']['sort_by'], 'custom_') !== 0) {
            return $query_conversations;
        }

        $sortBy = str_replace('custom_', '', $_REQUEST['sorting']['sort_by']);
        if (!preg_match('/^[A-Za-z0-9_]+$/', $sortBy)) {
            return $query_conversations;
        }

        $order = ($_REQUEST['sorting']['order'] ?? 'asc') === 'desc' ? 'desc' : 'asc';

        $query_conversations = $query_conversations->leftJoin(\DB::Raw('(select conversation_custom_field.custom_field_id, conversation_custom_field.conversation_id,conversation_custom_field.value,name from conversation_custom_field left join custom_fields on conversation_custom_field.custom_field_id = custom_fields.id where name LIKE \'' . $sortBy . '\') a'), 'a.conversation_id', '=', 'conversations.id');

        // FIX for #2 "Sorting of Custom Fields not working with Customer Folders"
        $query_conversations = $query_conversations->selectRaw('conversations.*, (CASE WHEN a.name LIKE \'' . $sortBy . '\' THEN a.value END) AS ' . $sortBy);
        // old Implementation
        // $query_conversations=  $query_conversations->selectRaw('*, (CASE WHEN a.name LIKE \''.$sortBy.'\' THEN a.value END) AS '. $sortBy);
        $query_conversations = $query_conversations->orderBy($sortBy, $order);

        return $query_conversations;
    }

    public function hooks()
    {
        // Add module's JS file to the application layout.
        \Eventy::addFilter('javascripts', function ($javascripts) {
            $javascripts[] = \Module::getPublicPath(CF_SORTABLE_MODULE) . '/js/module.js';
            return $javascripts;
        });

        \Eventy::addFilter('stylesheets', function ($styles) {
            $styles[] = \Module::getPublicPath(CF_SORTABLE_MODULE) . '/css/style.css';
            return $styles;
        });

        // Sort by custom fields (mailbox folders)
        \Eventy::addFilter('folder.conversations_query', function ($query_conversations) {
            return $this->applyCustomFieldSorting($query_conversations);
        });

        // Sort by custom fields (search + AJAX sort/pagination)
        \Eventy::addFilter('search.conversations.apply_filters', function ($query_conversations, $filters, $q) {
            return $this->applyCustomFieldSorting($query_conversations);
        }, 20, 3);

        \Eventy::addAction('conversations_table.col_before_conv_number', function ($conversation) {
            $custom_fields = $this->getListCustomFields();
            if (!$custom_fields->count()) {
                return;
            }

            foreach ($custom_fields as $custom_field) {
                $slug = $this->createSlug($custom_field->name, "_");
                ob_start();
                ?>
                    <col class="conv-<?= $slug ?>">
                <?php
                echo ob_get_clean();
            }
        }, 20, 3);

        \Eventy::addAction('conversations_table.th_before_conv_number', function () {
            $custom_fields = $this->getListCustomFields();
            if (!$custom_fields->count()) {
                return;
            }

            $sorting = ['sort_by' => 'date', 'order' => 'asc'];
            if (isset($_REQUEST['sorting'])) {
                $sorting['sort_by'] = request()->sorting['sort_by'];
                $sorting['order'] = request()->sorting['order'];
            }

            foreach ($custom_fields as $custom_field) {
                $slug = $this->createSlug($custom_field->name, "_");
                ob_start();
                ?>
                    <th class="custom-field-th">
                        <span class="conv-col-sort custom-field-tr" data-sort-by="custom_<?= $slug ?>" data-order="<?= ($sorting['sort_by'] == 'custom_' . $slug) ? $sorting['order'] : 'desc' ?>">
                            <?= __($custom_field->name) ?>
                            <?= ($sorting['sort_by'] == 'custom_' . $slug && $sorting['order'] == 'asc') ? '↓' : '' ?>
                            <?= ($sorting['sort_by'] == 'custom_' . $slug && $sorting['order'] == 'desc') ? '↑' : '' ?>
                        </span>
                    </th>
                <?php
                echo ob_get_clean();
            }
        }, 20, 3);

        \Eventy::addAction('conversations_table.td_before_conv_number', function ($conversation) {
            $custom_fields = $this->getListCustomFields();
            if (!$custom_fields->count()) {
                return;
            }

            $values_by_name = [];
            if (isset($conversation->custom_fields)) {
                foreach ($conversation->custom_fields as $custom_field) {
                    $values_by_name[$custom_field->name] = $custom_field;
                }
            }

            // Keep the same columns as headers (empty cell when a field is missing)
            foreach ($custom_fields as $list_field) {
                $custom_field = $values_by_name[$list_field->name] ?? null;
                ob_start();
                ?>
                     <td class="custom-field-td <?= $custom_field ? $this->createCSSClassForCustomField($custom_field) : '' ?>">
                     <?php if ($custom_field): ?>
                     <a href="<?= $conversation->url() ?>" title="<?= __('View conversation') ?>"><?= $custom_field->getAsText() ?></a>
                     <?php endif; ?>
                     </td>
                <?php
                echo ob_get_clean();
            }
        }, 20, 3);

        \Eventy::addAction('conversations_table.row_class', function ($conversation) {
            if (isset($conversation->custom_fields)) {
                foreach ($conversation->custom_fields as $custom_field) {
                    echo " ";
                    echo $this->createCSSClassForCustomField($custom_field);
                    echo " ";
                }
            }
        });
    }

    private function createCSSClassForCustomField($custom_field)
    {
        $propName = $this->createSlug($custom_field->name, "-");
        $propValue = $this->createSlug($custom_field->getAsText(), "-");
        return 'cf_' . $propName . '_' . $propValue;
    }

    /**
     * Register config.
     *
     * @return void
     */
    protected function registerConfig()
    {
        $this->publishes([
            __DIR__ . '/../Config/config.php' => config_path('srotablecustomfields.php'),
        ], 'config');
        $this->mergeConfigFrom(
            __DIR__ . '/../Config/config.php',
            'srotablecustomfields'
        );
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return [];
    }
}
