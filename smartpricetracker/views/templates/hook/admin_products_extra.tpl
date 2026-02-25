<div class="panel" style="margin-top: 20px;">
    <div class="panel-heading">
        <i class="icon-search"></i> Radar de Precios (Competencia)
        <span class="badge badge-info">v2.1.0</span>
    </div>
    <div class="panel-body">
        <div class="row">
            <div class="col-md-12">
                <div class="alert alert-info">
                    <i class="icon-info-circle"></i>
                    <strong>¿Cómo funciona?</strong> Este módulo busca automáticamente tu producto en Google Shopping y extrae los precios de la competencia.
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-8">
                <div class="form-group">
                    <label for="search_term_{$id_product}">
                        <i class="icon-search"></i> Término de búsqueda:
                    </label>
                    <input type="text" 
                           class="form-control" 
                           id="search_term_{$id_product}" 
                           value="{$search_term|escape:'html':'UTF-8'}" 
                           placeholder="Nombre del producto a buscar">
                    <p class="help-block">Puedes modificar el nombre para afinar la búsqueda</p>
                </div>
            </div>
            <div class="col-md-4" style="padding-top: 23px;">
                <button type="button" 
                        class="btn btn-primary btn-lg btn-block" 
                        id="btn_search_competitors_{$id_product}"
                        onclick="searchCompetitors({$id_product})">
                    <i class="icon-refresh"></i> Buscar Precios
                </button>
            </div>
        </div>

        <div id="loading_{$id_product}" style="display: none;" class="alert alert-warning">
            <i class="icon-spinner icon-spin"></i> Buscando precios en Google Shopping... Esto puede tardar unos segundos.
        </div>

        <div id="error_{$id_product}" style="display: none;" class="alert alert-danger">
            <i class="icon-warning"></i> <span id="error_message_{$id_product}"></span>
        </div>

        <div id="results_container_{$id_product}">
            {if $competitors && count($competitors) > 0}
                <div class="alert alert-success">
                    <i class="icon-check"></i> <strong>Tu precio actual:</strong> {$my_price|string_format:"%.2f"} € 
                    {if $last_scan}
                        <span class="pull-right"><small><i class="icon-clock-o"></i> Última búsqueda: {$last_scan}</small></span>
                    {/if}
                </div>

                <div class="table-responsive" style="max-height: 500px; overflow-y: auto;">
                    <table class="table table-striped table-bordered" id="competitors_table_{$id_product}">
                        <thead style="position: sticky; top: 0; background: #fff; z-index: 10;">
                            <tr>
                                <th width="5%">#</th>
                                <th width="35%"><i class="icon-shopping-cart"></i> Vendedor</th>
                                <th width="20%"><i class="icon-eur"></i> Precio</th>
                                <th width="20%"><i class="icon-sort-amount-desc"></i> Diferencia</th>
                                <th width="20%"><i class="icon-signal"></i> Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            {foreach from=$competitors item=comp key=index}
                                {assign var=diff value=$my_price-$comp.price}
                                <tr class="{if $diff > 0}success{elseif $diff < 0}danger{else}warning{/if}">
                                    <td class="text-center"><strong>{$index + 1}</strong></td>
                                    <td>
                                        <strong>{$comp.seller|escape:'html':'UTF-8'}</strong>
                                        {if $comp.url && $comp.url != '#'}
                                            <br><a href="{$comp.url|escape:'html':'UTF-8'}" target="_blank" class="btn btn-xs btn-default">
                                                <i class="icon-external-link"></i> Ver tienda
                                            </a>
                                        {/if}
                                    </td>
                                    <td class="text-center">
                                        <span class="badge badge-primary" style="font-size: 14px;">
                                            {$comp.price|string_format:"%.2f"} €
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        {if $diff > 0}
                                            <span class="label label-success">
                                                <i class="icon-arrow-down"></i> -{$diff|abs|string_format:"%.2f"} € (Más barato)
                                            </span>
                                        {elseif $diff < 0}
                                            <span class="label label-danger">
                                                <i class="icon-arrow-up"></i> +{$diff|abs|string_format:"%.2f"} € (Más caro)
                                            </span>
                                        {else}
                                            <span class="label label-warning">
                                                <i class="icon-minus"></i> Igual precio
                                            </span>
                                        {/if}
                                    </td>
                                    <td class="text-center">
                                        {if $diff > 0}
                                            <span class="label label-success"><i class="icon-thumbs-up"></i> Competitivo</span>
                                        {elseif $diff < 0}
                                            <span class="label label-danger"><i class="icon-warning"></i> Revisar</span>
                                        {else}
                                            <span class="label label-info"><i class="icon-balance-scale"></i> Equilibrado</span>
                                        {/if}
                                    </td>
                                </tr>
                            {/foreach}
                        </tbody>
                    </table>
                </div>

                <div class="alert alert-info" style="margin-top: 15px;">
                    <i class="icon-lightbulb-o"></i> 
                    <strong>Resumen:</strong>
                    Se encontraron <strong>{count($competitors)}</strong> competidores. 
                    {assign var=cheaper value=0}
                    {assign var=expensive value=0}
                    {foreach from=$competitors item=comp}
                        {assign var=diff value=$my_price-$comp.price}
                        {if $diff > 0}{assign var=cheaper value=$cheaper+1}{/if}
                        {if $diff < 0}{assign var=expensive value=$expensive+1}{/if}
                    {/foreach}
                    Eres más barato que <strong>{$cheaper}</strong> competidores y más caro que <strong>{$expensive}</strong>.
                </div>
            {else}
                <div class="alert alert-warning">
                    <i class="icon-info-circle"></i> No hay datos de competencia aún. Haz clic en "Buscar Precios" para comenzar.
                </div>
            {/if}
        </div>
    </div>
</div>

<script type="text/javascript">
function searchCompetitors(id_product) {
    var searchTerm = $('#search_term_' + id_product).val().trim();
    var ajaxUrl = '{$ajax_link|escape:'javascript':'UTF-8'}';
    
    if (searchTerm === '') {
        alert('Por favor, introduce un término de búsqueda');
        return;
    }

    // Mostrar loading
    $('#loading_' + id_product).show();
    $('#error_' + id_product).hide();
    $('#btn_search_competitors_' + id_product).prop('disabled', true).html('<i class="icon-spinner icon-spin"></i> Buscando...');

    $.ajax({
        url: ajaxUrl,
        type: 'POST',
        dataType: 'json',
        data: {
            ajax: true,
            action: 'SearchCompetitors',
            id_product: id_product,
            search_term: searchTerm
        },
        success: function(response) {
            $('#loading_' + id_product).hide();
            $('#btn_search_competitors_' + id_product).prop('disabled', false).html('<i class="icon-refresh"></i> Buscar Precios');

            if (response.success) {
                // Construir la tabla HTML con los resultados
                var html = '<div class="alert alert-success">';
                html += '<i class="icon-check"></i> <strong>Tu precio actual:</strong> ' + response.my_price_formatted;
                html += '<span class="pull-right"><small><i class="icon-clock-o"></i> Última búsqueda: ' + response.last_scan + '</small></span>';
                html += '</div>';

                html += '<div class="table-responsive" style="max-height: 500px; overflow-y: auto;">';
                html += '<table class="table table-striped table-bordered">';
                html += '<thead style="position: sticky; top: 0; background: #fff; z-index: 10;">';
                html += '<tr>';
                html += '<th width="5%">#</th>';
                html += '<th width="35%"><i class="icon-shopping-cart"></i> Vendedor</th>';
                html += '<th width="20%"><i class="icon-eur"></i> Precio</th>';
                html += '<th width="20%"><i class="icon-sort-amount-desc"></i> Diferencia</th>';
                html += '<th width="20%"><i class="icon-signal"></i> Estado</th>';
                html += '</tr>';
                html += '</thead>';
                html += '<tbody>';

                var cheaper = 0;
                var expensive = 0;

                $.each(response.competitors, function(index, comp) {
                    var rowClass = comp.diff > 0 ? 'success' : (comp.diff < 0 ? 'danger' : 'warning');
                    if (comp.diff > 0) cheaper++;
                    if (comp.diff < 0) expensive++;
                    
                    html += '<tr class="' + rowClass + '">';
                    html += '<td class="text-center"><strong>' + (index + 1) + '</strong></td>';
                    html += '<td><strong>' + comp.seller + '</strong>';
                    if (comp.url && comp.url !== '#') {
                        html += '<br><a href="' + comp.url + '" target="_blank" class="btn btn-xs btn-default"><i class="icon-external-link"></i> Ver tienda</a>';
                    }
                    html += '</td>';
                    html += '<td class="text-center"><span class="badge badge-primary" style="font-size: 14px;">' + comp.price_formatted + '</span></td>';
                    html += '<td class="text-center">';
                    
                    if (comp.diff > 0) {
                        html += '<span class="label label-success"><i class="icon-arrow-down"></i> -' + comp.diff_formatted + ' (Más barato)</span>';
                    } else if (comp.diff < 0) {
                        html += '<span class="label label-danger"><i class="icon-arrow-up"></i> +' + comp.diff_formatted + ' (Más caro)</span>';
                    } else {
                        html += '<span class="label label-warning"><i class="icon-minus"></i> Igual precio</span>';
                    }
                    
                    html += '</td>';
                    html += '<td class="text-center">';
                    
                    if (comp.diff > 0) {
                        html += '<span class="label label-success"><i class="icon-thumbs-up"></i> Competitivo</span>';
                    } else if (comp.diff < 0) {
                        html += '<span class="label label-danger"><i class="icon-warning"></i> Revisar</span>';
                    } else {
                        html += '<span class="label label-info"><i class="icon-balance-scale"></i> Equilibrado</span>';
                    }
                    
                    html += '</td>';
                    html += '</tr>';
                });

                html += '</tbody>';
                html += '</table>';
                html += '</div>';

                html += '<div class="alert alert-info" style="margin-top: 15px;">';
                html += '<i class="icon-lightbulb-o"></i> <strong>Resumen:</strong> ';
                html += 'Se encontraron <strong>' + response.competitors.length + '</strong> competidores. ';
                html += 'Eres más barato que <strong>' + cheaper + '</strong> competidores y más caro que <strong>' + expensive + '</strong>.';
                html += '</div>';

                $('#results_container_' + id_product).html(html);
            } else {
                $('#error_message_' + id_product).text(response.error);
                $('#error_' + id_product).show();
            }
        },
        error: function() {
            $('#loading_' + id_product).hide();
            $('#btn_search_competitors_' + id_product).prop('disabled', false).html('<i class="icon-refresh"></i> Buscar Precios');
            $('#error_message_' + id_product).text('Error de conexión. Por favor, inténtalo de nuevo.');
            $('#error_' + id_product).show();
        }
    });
}

// Auto-búsqueda al cargar la página si no hay datos previos
$(document).ready(function() {
    var id_product = {$id_product};
    var hasResults = {if $competitors && count($competitors) > 0}true{else}false{/if};
    
    // Si no hay resultados previos, buscar automáticamente
    if (!hasResults) {
        console.log('Iniciando búsqueda automática de precios...');
        setTimeout(function() {
            searchCompetitors(id_product);
        }, 500); // Pequeño delay para que cargue bien la interfaz
    }
});
</script>

<style>
#competitors_table_{$id_product} tbody tr.success {
    background-color: #dff0d8 !important;
}
#competitors_table_{$id_product} tbody tr.danger {
    background-color: #f2dede !important;
}
#competitors_table_{$id_product} tbody tr.warning {
    background-color: #fcf8e3 !important;
}
#competitors_table_{$id_product} thead {
    background-color: #f5f5f5;
}
.badge-primary {
    background-color: #00aff0;
}
</style>
