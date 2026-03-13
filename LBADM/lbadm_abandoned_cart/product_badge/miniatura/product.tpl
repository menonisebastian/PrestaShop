{**
 * 2007-2018 PrestaShop
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License 3.0 (AFL-3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/AFL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to http://www.prestashop.com for more information.
 *
 * @author    PrestaShop SA <contact@prestashop.com>
 * @copyright 2007-2018 PrestaShop SA
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 * International Registered Trademark & Property of PrestaShop SA
 *}
 {block name='product_miniature_item'}
 <article class="product-miniature js-product-miniature" data-id-product="{$product.id_product}" data-id-product-attribute="{$product.id_product_attribute}" itemscope itemtype="http://schema.org/Product">
   <div class="thumbnail-container">
   {block name='product_thumbnail'}
     {assign var=qty value=$product.quantity}
     {if $product.cover}
       <div class="lbadm-img-wrapper">
         <a href="{$product.url}" class="thumbnail product-thumbnail">
           <img
             src="{$product.cover.bySize.home_default.url}"
             alt="{if !empty($product.cover.legend)}{$product.cover.legend}{else}{$product.name|truncate:30:'...'}{/if}"
             data-full-size-image-url="{$product.cover.large.url}"
           >
         </a>
         {* ── ETIQUETA DE ESTADO — anclada solo a la imagen ── *}
         {if $qty > 5}
           <span class="lbadm-badge lbadm-stock-rapido lbadm-overlay">🚚 En stock · 24/48h</span>
         {elseif $qty > 0}
           <span class="lbadm-badge lbadm-ultimas lbadm-overlay">⚠ Últimas unidades</span>
         {elseif $product.availability == 'available'}
           <span class="lbadm-badge lbadm-pedido lbadm-overlay">📋 Bajo pedido</span>
         {else}
           <span class="lbadm-badge lbadm-agotado lbadm-overlay">✖ Agotado</span>
         {/if}
         {foreach from=$product.features item=feature}
           {if $feature.name == 'Estado del Producto' && $feature.value == 'Personalizado'}
             <span class="lbadm-badge lbadm-personalizado lbadm-overlay">✂ Personalizado</span>
           {/if}
         {/foreach}
         {* ── FIN ETIQUETA ── *}
       </div>
     {else}
       <a href="{$product.url}" class="thumbnail product-thumbnail">
         <img src="{$urls.no_picture_image.bySize.home_default.url}">
       </a>
     {/if}
   {/block}

     <div class="product-description">
     {block name='product_name'}
       {if $page.page_name == 'index'}
         <h3 class="h3 product-title" itemprop="name"><a href="{$product.url}">{$product.name|truncate:30:'...'}</a></h3>
       {else}
         <h2 class="h3 product-title" itemprop="name"><a href="{$product.url}">{$product.name|truncate:30:'...'}</a></h2>
       {/if}
     {/block}

       {block name='product_price_and_shipping'}
         {if $product.show_price}
           <div class="product-price-and-shipping">
             {if $product.has_discount}
               {hook h='displayProductPriceBlock' product=$product type="old_price"}

               <span class="sr-only">{l s='Regular price' d='Shop.Theme.Catalog'}</span>
               <span class="regular-price">{$product.regular_price}</span>
               {if $product.discount_type === 'percentage'}
                 <span class="discount-percentage discount-product">{$product.discount_percentage}</span>
               {elseif $product.discount_type === 'amount'}
                 <span class="discount-amount discount-product">{$product.discount_amount_to_display}</span>
               {/if}
             {/if}

             {hook h='displayProductPriceBlock' product=$product type="before_price"}

             <span class="sr-only">{l s='Price' d='Shop.Theme.Catalog'}</span>
             <span itemprop="price" class="price">{$product.price}</span>

             {hook h='displayProductPriceBlock' product=$product type='unit_price'}

             {hook h='displayProductPriceBlock' product=$product type='weight'}
           </div>
         {/if}
       {/block}

       {block name='product_reviews'}
         {hook h='displayProductListReviews' product=$product}
       {/block}

       {block name='add_to_cart_button'}
         {if $product.add_to_cart_url}
           <div class="product-add-to-cart">
             <form action="{$urls.pages.cart}" method="post" class="add-to-cart-or-refresh">
               <input type="hidden" name="token" value="{$static_token}">
               <input type="hidden" name="id_product" value="{$product.id_product}">
               <input type="hidden" name="qty" value="1">
               <button class="btn btn-primary add-to-cart" data-button-action="add-to-cart">
                 {l s='Add to cart' d='Shop.Theme.Actions'}
               </button>
             </form>
           </div>
         {/if}
       {/block}

     </div>

     {block name='product_flags'}
       <ul class="product-flags">

         {foreach from=$product.flags item=flag}
           <li class="product-flag {$flag.type}">{$flag.label}</li>
         {/foreach}
             
       </ul>
     {/block}

     <div class="highlighted-informations{if !$product.main_variants} no-variants{/if} hidden-sm-down">
       {block name='quick_view'}
         <a class="quick-view" href="#" data-link-action="quickview">
           <i class="material-icons search">&#xE8B6;</i> {l s='Quick view' d='Shop.Theme.Actions'}
         </a>
       {/block}

       {block name='product_variants'}
         {if $product.main_variants}
           {include file='catalog/_partials/variant-links.tpl' variants=$product.main_variants}
         {/if}
       {/block}
     </div>

   </div>
 </article>
{/block}
