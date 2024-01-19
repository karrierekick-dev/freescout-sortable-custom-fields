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
    public function hooks()
    {
          // Add module's JS file to the application layout.
          \Eventy::addFilter('javascripts', function ($javascripts) {
     
            $javascripts[] = \Module::getPublicPath(CF_SORTABLE_MODULE) . '/js/module.js';

            return $javascripts;
        });

       
        \Eventy::addFilter('stylesheets', function($styles) {
            $styles[] = \Module::getPublicPath(CF_SORTABLE_MODULE).'/css/style.css';
            return $styles;
        });

        // Sort by custom fields
        \Eventy::addFilter('folder.conversations_query', function ($query_conversations) {

            if (isset($_REQUEST['sorting'])&& strpos($_REQUEST['sorting']['sort_by'], 'custom_') === 0) {
                $sortBy = str_replace('custom_','',$_REQUEST['sorting']['sort_by']);
                $query_conversations = $query_conversations->leftJoin(\DB::Raw('(select conversation_custom_field.custom_field_id, conversation_custom_field.conversation_id,conversation_custom_field.value,name from conversation_custom_field left join custom_fields on conversation_custom_field.custom_field_id = custom_fields.id where name LIKE \''.$sortBy.'\') a'),'a.conversation_id','=','conversations.id');
             
                $query_conversations=  $query_conversations->selectRaw('*, (CASE WHEN a.name LIKE \''.$sortBy.'\' THEN a.value END) AS '. $sortBy);
                  $query_conversations=  $query_conversations->orderBy($sortBy,$_REQUEST['sorting']['order']);
            }
            return $query_conversations;

        }); 


        \Eventy::addAction('conversations_table.col_before_conv_number', function ($conversation) {

            $mailbox_id = request()->mailbox_id ?? request()->id ?? 0;
       
        if ($mailbox_id) {
            $custom_fields = CustomField::where('mailbox_id', $mailbox_id)
                // groupBy('name') does not work in PostgreSQL.
                ->distinct('name')
                ->get();
        }


        if (isset($custom_fields) && count($custom_fields)) {
            foreach ($custom_fields as $custom_field) {
                if (!$custom_field->show_in_list){
                    continue;
                }
                $slug= $this->createSlug($custom_field->name, "_");
                ob_start()
                    ?>
                    <col class="conv-<?=  $slug ?>">
               
                <?php
                $output = ob_get_clean();
                echo $output;
            }
        }
        }, 20, 3);
        \Eventy::addAction('conversations_table.th_before_conv_number', function () {
            $sorting=['sort_by'=>'date','order'=>'asc'];

            if ( isset($_REQUEST['sorting'])){  
                $sorting['sort_by'] = request()->sorting['sort_by'];
                $sorting['order'] = request()->sorting['order'];
              

            }
          
            $mailbox_id = request()->mailbox_id ?? request()->id ?? 0;
       
            if ($mailbox_id) {
                $custom_fields = CustomField::where('mailbox_id', $mailbox_id)
                    // groupBy('name') does not work in PostgreSQL.
                    ->distinct('name')
                    ->get();
            }


            if (isset($custom_fields) && count($custom_fields)) {
                foreach ($custom_fields as $custom_field) {
                    if (!$custom_field->show_in_list){
                        continue;
                    }
                    $slug= $this->createSlug($custom_field->name, "_");
                    ob_start()
                        ?>
                    <th class="custom-field-th">
                        <span class="conv-col-sort custom-field-tr" data-sort-by="custom_<?=  $slug ?>" data-order="<?=  ($sorting['sort_by'] ==  'custom_'.$slug) ? $sorting['order']:'desc' ?>">
                            <?=  __($custom_field->name) ?>
                            <?= ($sorting['sort_by'] == 'custom_'.$slug && $sorting['order'] =='asc')? '↓' : '' ?>
                            <?= ($sorting['sort_by'] == 'custom_'.$slug && $sorting['order'] =='desc')? '↑' : ''?>
                        </span>
                    </th>
                    <?php
                    $output = ob_get_clean();
                    echo $output;
                }
            }


        }, 20, 3);

     \Eventy::addAction('conversations_table.td_before_conv_number', function ($conversation) {
         if (isset($conversation->custom_fields)){
            foreach ($conversation->custom_fields as $custom_field){
                ob_start()
                ?>
                
                     <td class="custom-field-td <?= $this->createCSSClassForCustomField($custom_field) ?>">
                     <a href="<?= $conversation->url() ?>" title="<?= __('View conversation') ?>"><?= $custom_field->getAsText() ?></a>
                     </td>
                <?php
                $output = ob_get_clean();
                echo $output;
            }
        }
      

        }, 20, 3);
     
        \Eventy::addAction('conversations_table.row_class', function($conversation) {
       
            if (isset($conversation->custom_fields)){
                
                foreach ($conversation->custom_fields as $custom_field){
                    echo " ";
                    echo $this->createCSSClassForCustomField($custom_field);
                    echo " ";
                }
            }
          
        });
    
    }

    private function createCSSClassForCustomField($custom_field) {
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
