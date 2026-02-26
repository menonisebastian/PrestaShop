<div class="panel">
    <div class="panel-heading">
        <i class="icon-search"></i> {l s='Radar de Precios (Competencia)' mod='smartpricetracker'}
    </div>
    
    <div class="alert alert-info">
        <i class="icon-info-sign"></i> <strong>¿Cómo funciona?</strong> Este módulo busca automáticamente tu producto en Google Shopping y extrae los precios de la competencia.
    </div>

    <div class="row" style="margin-bottom: 20px;">
        <div class="col-md-12">
            <label class="control-label">Término de búsqueda:</label>
            <div class="input-group">
                <input type="text" id="smart_search_term" class="form-control" value="{$search_term|escape:'htmlall':'UTF-8'}">
                <span class="input-group-btn">
                    <button type="button" id="btn-search-prices" class="btn btn-info" style="background-color: #2eacce; border-color: #2eacce; color: white;">
                        Buscar Precios
                    </button>
                </span>
            </div>
            <p class="help-block"><i>Puedes modificar el nombre para afinar la búsqueda</i></p>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table" style="border: 1px solid #ddd;">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Vendedor</th>
                    <th>Precio</th>
                    <th>Diferencia</th>
                    <th>Estado</th>
                </tr>
            </thead>
            <tbody id="competitors-list">
                {if isset($competitors) && is_array($competitors) && count($competitors) > 0}
                    {foreach from=$competitors item=comp name=comp_list}
                        {assign var="diff" value=$my_price - $comp.price}
                        {if $diff > 0}
                            {assign var="row_color" value="#f9e6e6"}
                            {assign var="estado" value="Revisar"}
                            {assign var="diff_text" value="+"|cat:($diff|number_format:2:'.':'')|cat:" € (Más caro)"}
                        {else}
                            {assign var="row_color" value="#e8f4e8"}
                            {assign var="estado" value="Competitivo"}
                            {assign var="diff_text" value=($diff|number_format:2:'.':'')|cat:" € (Más barato)"}
                        {/if}
                        <tr style="background-color: {$row_color};">
                            <td>{$smarty.foreach.comp_list.iteration}</td>
                            <td><strong>{$comp.seller|escape:'htmlall':'UTF-8'}</strong></td>
                            <td><span style="background-color: #00a8cc; color: white; padding: 3px 8px; border-radius: 3px; font-weight: bold;">{$comp.price|number_format:2:'.':''} €</span></td>
                            <td>{$diff_text}</td>
                            <td>{$estado}</td>
                        </tr>
                    {/foreach}
                {else}
                    <tr>
                        <td colspan="5" class="text-center">No hay datos recientes. Haz clic en Buscar Precios.</td>
                    </tr>
                {/if}
            </tbody>
        </table>
    </div>
</div>

<script type="text/javascript">
    $(document).ready(function() {
        $('#btn-search-prices').off('click').on('click', function(e) {
            e.preventDefault();
            var btn = $(this);
            var searchTerm = $('#smart_search_term').val();
            var idProduct = {$id_product|intval};
            
            btn.text('Buscando...').prop('disabled', true);
            
            $.ajax({
                url: '{$ajax_link|escape:"javascript":"UTF-8"}',
                type: 'POST',
                dataType: 'json',
                data: {
                    ajax: true,
                    action: 'SearchCompetitors',
                    id_product: idProduct,
                    search_term: searchTerm
                },
                success: function(response) {
                    btn.text('Buscar Precios').prop('disabled', false);
                    
                    if (response.success && response.competitors.length > 0) {
                        var html = '';
                        $.each(response.competitors, function(index, comp) {
                            var num = index + 1;
                            var diff = response.my_price - comp.price;
                            
                            var rowColor = diff > 0 ? '#f9e6e6' : '#e8f4e8';
                            var estado = diff > 0 ? 'Revisar' : 'Competitivo';
                            var diffText = diff > 0 ? '+' + diff.toFixed(2) + ' € (Más caro)' : diff.toFixed(2) + ' € (Más barato)';
                            
                            // Si el vendedor está vacío o no lo encuentra, usa un texto genérico, 
                            // pero normalmente traerá el nombre del dominio (.com, .es)
                            var sellerName = (comp.seller && comp.seller !== '') ? comp.seller : 'Competidor';
                            
                            html += '<tr style="background-color: ' + rowColor + ';">';
                            html += '<td>' + num + '</td>';
                            html += '<td><strong>' + sellerName + '</strong></td>';
                            html += '<td><span style="background-color: #00a8cc; color: white; padding: 3px 8px; border-radius: 3px; font-weight: bold;">' + parseFloat(comp.price).toFixed(2) + ' €</span></td>';
                            html += '<td>' + diffText + '</td>';
                            html += '<td>' + estado + '</td>';
                            html += '</tr>';
                        });
                        $('#competitors-list').html(html);
                    } else {
                        alert(response.error || 'No se encontraron resultados para este producto.');
                    }
                },
                error: function() {
                    btn.text('Buscar Precios').prop('disabled', false);
                    alert('Error de conexión al procesar la búsqueda.');
                }
            });
        });
    });
</script>