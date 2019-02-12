<?php

defined( 'ABSPATH' ) || die( 'No direct access allowed!' );

$counter = 1;

?>

<div class="wpap-container wpap-newreleases-row">
    <div class="wpap-row">

    <?php foreach ($items as $item): ?>
        
        <div class="wpap-row-item">
            <div class="wpap-row-item-notification wpap-item-bestseller-position">
                BESTSELLER NO <?php echo $counter++ ?>
            </div>
            <div class="wpap-row-image-wrapper">
                <a href="<?php echo $item["detailPage"] ?>" target="_blank">
                    <img class="wpap-row-image" src="<?php if (isset($item["image"]["URL"])) echo $item["image"]["URL"] ?>">
                </a>
            </div>
            <div class="wpap-row-title">
                <?php echo $item["title"] ?>
            </div>
            <div class="wpap-row-price">
                <div class="wpap-price-wrapper">
                    <div class="wpap-price-main"><?php echo $item["price"]["main"] ?></div>
                    <?php if (isset($item["price"]["saved"])): ?>
                        <div class="wpap-price-saved">-<?php echo $item["price"]["saved"] ?> (<?php  echo $item["price"]["percentage"] ?>%)</div>
                    <?php endif; ?>
                    <?php if (isset($item['price']['prime']) && $item['price']['prime']): ?>
                        <img class="wpap-row-prime" src="<?php echo $this->plugin_dir_url ?>/img/prime-logo.png">
                    <?php endif; ?>
                </div>
            </div>
            <div class="wpap-row-buy">
                <a class="wpap-buy-button-link" href="<?php echo $item["detailPage"] ?>" target="_blank">
                    <div class="wpap-buy-button">
                        BUY ON AMAZON
                    </div>
                </a>
            </div>
        </div>
       
    <?php endforeach; ?>
        
    </div>
</div>