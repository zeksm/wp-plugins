<?php

defined( 'ABSPATH' ) || die( 'No direct access allowed!' );

?>

<tr class='wpap-table-row'>
    
    <td class='wpap-table-cell wpap-table-head'>
        <?php echo $row_label ?>
    </td>

    <?php if ($is_custom_row): ?>

        <?php foreach($cell_values as $cell_value): ?>
        
            <td class='wpap-table-cell'>
                <?php echo $cell_value ?>
            </td>
        
        <?php endforeach?>
        
    <?php else: ?>

        <?php foreach ($items as $item): ?>
        
            <?php if ($row_value === "thumb"): ?>
            
                <td class='wpap-table-cell'>
                    <a href='<?php echo $item['detailPage'] ?>' target='_blank'>
                        <img class='wpap-table-thumb' src='<?php echo $item['image']['URL'] ?>'>
                    </a>
                </td>
                
            <?php elseif ($row_value === "title"): ?>
            
                <td class='wpap-table-cell'>
                    <a href='<?php echo $item['detailPage'] ?>' target='_blank'>
                        <?php echo $item['title'] ?>
                    </a>
                </td>
            
            <?php elseif ($row_value === "price"): ?>
            
                <td class='wpap-table-cell'>
                    <?php if (isset($item["price"]["main"])): ?>
                        <div class='wpap-price-wrapper'>
                            <div class='wpap-price-main'>
                                <?php echo $item["price"]["main"] ?>
                            </div>
                            <?php if (isset($item["price"]["saved"])): ?>
                                <div class='wpap-price-saved'>
                                    -<?php echo $item["price"]["saved"] ?> (<?php echo $item["price"]["percentage"] ?>%)
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </td>
                
            <?php elseif ($row_value === "prime"): ?>
            
                <td class='wpap-table-cell'>
                    <?php if (isset($item['price']['prime']) && $item['price']['prime']): ?>
                        <img class="wpap-item-prime" src="<?php echo $this->plugin_dir_url ?>/img/prime-logo.png">
                    <?php else: ?>
                        <img class="wpap-item-no-prime" src="<?php echo $this->plugin_dir_url ?>/img/no.png">
                    <?php endif; ?>
                </td>
                
            <?php elseif ($row_value === "buy"): ?>
            
                <td class='wpap-table-cell'>
                    <a href='<?php echo $item['detailPage'] ?>' target='_blank'>
                        <div class='wpap-buy-button'>
                            BUY <span class='wpap-buy-button-small-hide'>ON AMAZON</span>
                        </div>
                    </a>
                </td>
                
            <?php elseif ($row_value === "rating"): ?>
                
                <td class='wpap-table-cell'>
                    <?php if (isset($item["rating"])): ?>  
                        <!--<div class='wpap-item-rating'>
                            <?php //echo $item["rating"] ?>
                        </div>-->
                        <div class="wpap-item-rating-stars" title="<?php echo $item["rating"] ?> stars">
                            <div class="wpap-empty-stars"></div>
                            <div class="wpap-full-stars" style="width:<?php echo $item["percentage_rating"]?>%"></div>
                        </div>
                    <?php else: ?>
                        No ratings available
                    <?php endif; ?>
                </td>
                
            <?php elseif ($row_value === "reviews"): ?>
            
                <td class='wpap-table-cell'>
                    <?php if (isset($item["reviews_num"])): ?>  
                        <a href='<?php echo $item["reviews_url"] ?>' target='_blank'>
                            <div class='wpap-item-reviews-num'>
                                <?php echo $item["reviews_num"] ?>
                            </div>
                        </a>
                    <?php else: ?>
                        No reviews available
                    <?php endif; ?>
                </td>
                
            <?php endif; ?>

        <?php endforeach; ?>
        
    <?php endif; ?>
    
</tr>