# About
This FreeScout module makes Custom Fields sortable in [FreeScout](https://freescout.net).

This is a custom module and requires an existing [FreeScout](https://freescout.net) installation


# Installation
You may [install this module like any other FreeScout module](https://github.com/freescout-helpdesk/freescout/wiki/FreeScout-Modules#2-installing-official-modules).

Go to the "src" directory of this repository and copy the folder "SortableCustomFields" into your "Modules" folder of your [FreeScout](https://freescout.net) installation.

Go to the "Modules" section in FreeScout and activate "Sortable Custom Fields"

# Usage
Every Custom Field you create in the Custom Fields module becomes a column in your conversation tables and is sortable by its values.

You may style your rows with CSS depending on the defined Custom Field.

Each Custom Field will add a class to the tr element in the table. 
(It will be created using Laravels [Str::slug() class](https://laravel.com/docs/10.x/strings#method-str-slug))

Assuming you have a Custom Field "Priority" with the values 
    * "High"
    * "Medium"
    * "Low"
    * "New idea"

You will get the following CSS classes 
    * "cf_priority_high"
    * "cf_priority_medium"
    * "cf_priority_low"
    * "cf_priority_new-idea"

You may use the official FreeScout [Customization & Rebranding Module](https://freescout.net/module/customization/) to add your own CSS.

# Future Ideas
* Define the position where a Custom Field column should be placed to.
* Make a selection to select which columns should be visible (independent of the setting in Custom Fields). This should be saved as a property of each user's profile.
* Editable values for Custom Fields directly in the table