
This plugin allows you to tag any arbitrary post type as a "Resource"

All resources are listed in a common archive, and are fully searchable.

**Attributes**
In the plugin settings you can configure a set of fields called "Resource Attributes"

Each resource can define these attributes, and have these attributes appear in search forms.

For example, if your resources are all Videos, you might want to have an attribute like "Video Length" or "Source Website" (where the options are YouTube, Vimeo, etc)

**Custom Post Type**
By default many different kinds of post types can be defined as resources. Posts, pages, and media are all possible.

However, if you want to keep your resource collection more centralized and straightforward, you can enable a custom "Resource" post type. With all the regular functionality of resources, and a simple text descriptions.

**Shortcodes**
Each resource has the option to embed a list of it's attributes, and a list of related resources at the bottom of the content. However, if you want to customize the location of these elements, or if you want to embed lists and attributes on other pages you can use the following two shortcodes.

`[cres_attributes]`
Embeds a list of attributes for a given post
 * **post_id**, *int*  the ID of the post where attributes should be drawn from. This will default to the current post, if you embed it within a post's content.
 * **title**, *string*  a title to display above the attributes. This defaults to "Attributes", you can also set `title=""` to disable the title.

`[cres_list]`
Embeds a list of resources
 * **title**, *string*  a title to display above the list. This defaults to "Other Resources", you can also set `title=""` to disable the title.
 * **category**, *int*  the category ID for the category that you want to restrict the results to. Defaults to all categories. You can set `category=""` to render all categories
 * **limit**, *int*  the maximum number of resources to display. Defaults to 10. You can also set this value to 0, if you only want to display a search form.
 * **search**, *on/off*  Whether a search form should be displayed. Defaults to your global "search enabled" setting. Which can be defined in the admin Resource Settings.

