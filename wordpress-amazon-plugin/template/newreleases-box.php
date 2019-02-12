<?php

defined( 'ABSPATH' ) || die( 'No direct access allowed!' );

foreach ($items as $item):
?>

    <div class="wpap-container wpap-newreleases">
        <div class="wpap-item-list-notification wpap-item-newrelease">
            NEW PRODUCT
        </div>
        <div class="wpap-item">
            <div class="wpap-item-side">
                <div class="wpap-item-image-wrapper">
                    <a href="<?php echo $item["detailPage"] ?>">
                        <img class="wpap-item-image" src="<?php if (isset($item["image"]["URL"])) echo $item["image"]["URL"] ?>" target="_blank">
                    </a>
                </div>
                <a class="wpap-buy-button-link" href="<?php echo $item["detailPage"] ?>" target="_blank">
                    <div class='wpap-buy-button'>
                        BUY ON AMAZON
                    </div>
                </a>
            </div>
            <div class="wpap-item-main">
                <div class="wpap-item-title">
                    <?php echo $item["title"] ?>
                </div>
                <div class="wpap-item-description">
                    <ul>
                        <?php foreach ($item["description"] as $part): ?>
                            <li><?php echo $part ?></li>
                        <?php endforeach ?>
                    </ul>
                </div>
                <?php if (isset($item["rating"])): ?>
                    <div class="wpap-item-main-review">
                        <div class="wpap-item-rating">
                            <div class="wpap-item-rating-stars" title="<?php echo $item["rating"] ?> stars">
                                <div class="wpap-empty-stars"></div>
                                <div class="wpap-full-stars" style="width:<?php echo $item["percentage_rating"]?>%"></div>
                            </div>
                        </div>
                        <a href="<?php echo $item["reviews_url"]?>" target="_blank">
                            <div class="wpap-item-reviews-num"><?php echo $item["reviews_num"] ?></div>
                        </a>
                    </div>
                <?php endif; ?>
                <div class="wpap-item-main-bottom">
                    <div class="wpap-item-price">
                        <?php if (isset($item["price"]["main"])): ?>
                            <div class="wpap-price-wrapper">
                                <div class="wpap-price-main"><?php echo $item["price"]["main"] ?></div>
                                <?php if (isset($item["price"]["saved"])): ?>
                                    <div class="wpap-price-saved">-<?php echo $item["price"]["saved"] ?> (<?php  echo $item["price"]["percentage"]?>%)</div>
                                    <?php //echo $item["price"]["old"] ?>
                                <?php endif ?>
                                <?php if (isset($item['price']['prime']) && $item['price']['prime']): ?>
                                    <img class="wpap-item-prime" src="<?php echo $this->plugin_dir_url ?>/img/prime-logo.png">
                                <?php endif; ?>
                            </div>
                        <?php endif ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
<?php
endforeach;