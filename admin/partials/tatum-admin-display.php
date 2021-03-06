<?php

?>

    <!-- This file should primarily consist of HTML with a little bit of PHP. -->
    <div class="wrap">

        <h2><?php 
echo esc_html(get_admin_page_title());
?>
</h2>

        <form method="post" name="tatum_options" action="options.php">
			<?php 
//Grab all options
$options = get_option($this->plugin_name);
$active_api_key = $this->get_active_api_key();
?>
			<?php 
settings_fields($this->plugin_name);
do_settings_sections($this->plugin_name);
?>

            <table class="form-table">
                <tbody>
                <tr>
                    <th><label for="api_key">Select api key</label></th>
                    <td>
                        <select name="<?php 
echo $this->plugin_name;
?>
[api_key]" id="<?php 
echo $this->plugin_name;
?>
_api_key">
							<?php 
if (!isset($active_api_key)) {
    ?>
                                <option value="select" selected>Select API key</option>
							<?php 
}
?>
							<?php 
foreach ($this->get_contract_address_obtained_api_keys() as $key) {
    ?>

                                <option value="<?php 
    echo $key->post_title;
    ?>
"
									<?php 
    echo isset($active_api_key) && $key->ID == $active_api_key['tatum_api_key']->ID ? 'selected' : '';
    ?>
                                >
									<?php 
    echo $key->post_title;
    ?>
                                </option>
							<?php 
}
?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label for="automatic_minting">Automatic product minting</label></th>
                    <td>
                        <input type="checkbox" name="<?php 
echo $this->plugin_name;
?>
[automatic_minting]"
                               id="<?php 
echo $this->plugin_name;
?>
_automatic_minting"
							<?php 
echo isset($options['automatic_minting']) ? 'checked' : '';
?>
/>
                    </td>
                </tr>
                <tr>
                    <th><label for="url">URL of metadata token</label></th>
                    <td>
                        <input type="text" name="<?php 
echo $this->plugin_name;
?>
[metadata_url]"
                               id="<?php 
echo $this->plugin_name;
?>
_metadata_url"
                               value="<?php 
echo isset($options['metadata_url']) ? $options['metadata_url'] : '';
?>
"/>
                        <p class="description">If you checked automatic product minting, please provide URL of metadata
                            which will be set to each token on minting.</p>
                    </td>
                </tr>
            </table>
            <!-- Optional title for quotes list -->

            <p class="submit">
				<?php 
submit_button('Save all changes', 'primary', 'submit', true);
?>
            </p>
        </form>


    </div>

<?php 
settings_fields($this->plugin_name);