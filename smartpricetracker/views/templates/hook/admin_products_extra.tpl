{* Plantilla HTML Nativa para PrestaShop 8.1.x (Bootstrap 4) *}
<div class="card mt-3">
    <div class="card-header">
        <i class="material-icons" style="vertical-align: middle;">search</i> Radar de Precios en Google Shopping
    </div>
    
    <div class="card-body">
        <div class="alert alert-info" role="alert">
            <p class="alert-text">El sistema buscará este producto en Google Shopping y te mostrará los precios de las tiendas de la competencia en tiempo real.</p>
        </div>
        
        <div class="form-group mb-4">
            <label class="form-control-label font-weight-bold">Nombre del producto a buscar:</label>
            
            <div class="input-group">
                <input type="text" id="spt_search_term" class="form-control" value="{$search_term|escape:'html':'UTF-8'}" placeholder="Ej: JBL Eon One Compact">
                <div class="input-group-append">
                    <button class="btn btn-primary" type="button" id="spt_btn_scan">
                        <i class="material-icons" style="vertical-align: middle;">radar</i> Buscar Ahora
                    </button>
                </div>
            </div>
            <small class="form-text text-muted">Añade la marca y el modelo exacto si la búsqueda es muy genérica.</small>
        </div>

        <div id="spt_my_price_banner" class="alert alert-success text-center {if empty($competitors)}d-none{/if}">
            <strong>Tu Precio Actual (PVP):</strong> <span id="spt_my_price_text" class="lead ml-2">{$my_price|number_format:2:',':'.'} €</span>
        </div>

        <div id="spt_results_area" class="{if empty($competitors)}d-none{/if}">
            <div class="table-responsive mt-3">
                <table class="table table-striped table-bordered">
                    <thead class="thead-light">
                        <tr>
                            <th>Tienda Competidora</th>
                            <th class="text-center">Precio</th>
                            <th class="text-center">Diferencia Contigo</th>
                            <th class="text-center">Enlace</th>
                        </tr>
                    </thead>
                    <tbody id="spt_competitors_tbody">
                        {if !empty($competitors)}
                            {foreach from=$competitors item=comp}
                                <tr>
                                    <td class="align-middle"><strong>{$comp.store|escape:'html':'UTF-8'}</strong></td>
                                    <td class="text-center align-middle font-weight-bold">{$comp.price|number_format:2:',':'.'} €</td>
                                    <td class="text-center align-middle">
                                        {if $comp.diff > 0}
                                            <span class="badge badge-danger">+{$comp.diff|abs|number_format:2:',':'.'} € (Eres más caro)</span>
                                        {elseif $comp.diff < 0}
                                            <span class="badge badge-success">-{$comp.diff|abs|number_format:2:',':'.'} € (Eres más barato)</span>
                                        {else}
                                            <span class="badge badge-warning">Igual</span>
                                        {/if}
                                    </td>
                                    <td class="text-center align-middle">
                                        <a href="{$comp.url|escape:'html':'UTF-8'}" target="_blank" class="btn btn-sm btn-outline-secondary">Ver Web</a>
                                    </td>
                                </tr>
                            {/foreach}
                        {/if}
                    </tbody>
                </table>
            </div>
            
            <div class="text-right mt-2 text-muted small">
                Última búsqueda: <span id="spt_last_scan">{if $last_scan}{$last_scan|date_format:"%d/%m/%Y %H:%M:%S"}{/if}</span>
            </div>
        </div>

        <div id="spt_loader" class="text-center d-none mt-4 py-4">
            <div class="spinner-border text-primary" role="status" style="width: 3rem; height: 3rem;">
                <span class="sr-only">Cargando...</span>
            </div>
            <h5 class="mt-3">Analizando Google Shopping...</h5>
            <p class="text-muted">Esto puede tardar unos 15-30 segundos. Por favor, no cierres esta pestaña.</p>
        </div>
        
        <div id="spt_error_msg" class="alert alert-danger d-none mt-3" role="alert"></div>
    </div>
</div>

<script type="text/javascript">
document.body.addEventListener('click', function(e) {
    var btnScan = e.target.closest('#spt_btn_scan');
    if (!btnScan) return; 
    
    e.preventDefault();
    
    var searchTerm = document.getElementById('spt_search_term').value;
    if(!searchTerm) {
        alert("Por favor, introduce el nombre del producto que quieres buscar.");
        return;
    }

    document.getElementById('spt_error_msg').classList.add('d-none');
    document.getElementById('spt_loader').classList.remove('d-none');
    document.getElementById('spt_results_area').classList.add('d-none');
    document.getElementById('spt_my_price_banner').classList.add('d-none');
    
    btnScan.disabled = true;

    var formData = new FormData();
    formData.append('ajax', 1);
    formData.append('action', 'searchCompetitors');
    formData.append('id_product', '{$id_product|intval}');
    formData.append('search_term', searchTerm);

    fetch('{$ajax_link|escape:'javascript':'UTF-8'}', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        btnScan.disabled = false;
        document.getElementById('spt_loader').classList.add('d-none');

        if (data.success) {
            document.getElementById('spt_my_price_banner').classList.remove('d-none');
            document.getElementById('spt_results_area').classList.remove('d-none');
            
            document.getElementById('spt_my_price_text').innerText = data.my_price_formatted;
            document.getElementById('spt_last_scan').innerText = data.last_scan;

            var tbody = document.getElementById('spt_competitors_tbody');
            tbody.innerHTML = ''; 

            data.competitors.forEach(function(comp) {
                var tr = document.createElement('tr');
                
                var badgeHtml = '';
                if (comp.diff > 0) {
                    badgeHtml = '<span class="badge badge-danger">+' + comp.diff_formatted + ' (Eres más caro)</span>';
                } else if (comp.diff < 0) {
                    badgeHtml = '<span class="badge badge-success">-' + comp.diff_formatted + ' (Eres más barato)</span>';
                } else {
                    badgeHtml = '<span class="badge badge-warning">Igual</span>';
                }

                tr.innerHTML = `
                    <td class="align-middle"><strong>${comp.store}</strong></td>
                    <td class="text-center align-middle font-weight-bold">${comp.price_formatted}</td>
                    <td class="text-center align-middle">${badgeHtml}</td>
                    <td class="text-center align-middle">
                        <a href="${comp.url}" target="_blank" class="btn btn-sm btn-outline-secondary">Ver Web</a>
                    </td>
                `;
                tbody.appendChild(tr);
            });
        } else {
            var errorDiv = document.getElementById('spt_error_msg');
            errorDiv.innerText = data.error;
            errorDiv.classList.remove('d-none');
            document.getElementById('spt_results_area').classList.remove('d-none'); 
        }
    })
    .catch(error => {
        btnScan.disabled = false;
        document.getElementById('spt_loader').classList.add('d-none');
        
        var errorDiv = document.getElementById('spt_error_msg');
        errorDiv.innerText = "Error de conexión o Tiempo de Espera Agotado. La búsqueda en Google Shopping ha tardado demasiado.";
        errorDiv.classList.remove('d-none');
    });
});
</script>