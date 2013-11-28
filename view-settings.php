<div class="wrap">
    <h2>Website Monetization by MageNet</h2>
    <p>By installing WordPress plugin, you allow MageNet to display paid contextual ads on pages where where you grant permission via Pages options.</p>
    <p>Ad placement and removal process takes 3 hours. Please be patient.</p>
    <div class="tool-box">
        <h2 class="title">MageNet Key:</h2>
        <form method="post" action="">
            <input type="text" name="key" value="<?php echo $magenet_key;?>" />
            <input type="submit" name="submit" value="Save" />
            <?php echo $result_text; ?>
        </form>
    </div>
    <?if ($magenet_key) { ?>
    <div class="tool-box">
        <h3 class="title">Active Ads:</h2>
        <table class="widefat">	 
            <thead>
                <tr class="table-header">
                    <th>Page URL</th>
                    <th>Ads content</th>
                </tr>
            </thead>    	  
            <tbody>
            <?php if (count($link_data) > 0): ?>
            <?php foreach ($link_data as $key => $record): ?>
                <tr>
                    <td class="url">  
                        <?php echo $record['page_url'] ?>
                    </td>
                    <td class="link">
                        <?php echo $record['link_html'] ?>
                    </td>
                </tr>
            <?php endforeach; ?>            
            <?php else:?>
                <tr>
                    <td colspan="2" style="text-align:center">No Ads</td>
                </tr>
            <?php endif;?>
            </tbody>        
        </table> 
    </div>
    <div class="tool-box">
        <form method="post" action="">
            <input type="hidden" name="update_data" value="1" />
            <input type="submit" name="submit" value="Refresh Ads" />
        </form>
    </div>
    <? } ?>
</div>