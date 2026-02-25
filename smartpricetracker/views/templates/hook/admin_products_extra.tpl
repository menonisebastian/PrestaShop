{* Plantilla HTML que dibuja el interfaz en la pestaña del producto *}
<div class="card panel mt-3">
    <div class="card-header panel-heading">
        <i class="icon-money"></i> Smart Price Tracker
    </div>
    
    <div class="card-body panel-body">
        <p class="text-muted mb-4">Introduce la URL del producto de tu competencia. El sistema analizará los datos estructurados (Schema.org) para extraer el precio de forma limpia y comparar automáticamente.</p>
        
        <div class="form-group row mb-4">
            <label class="form-control-label control-label col-md-3 text-right text-md-right">URL del Competidor</label>
            <div class="col-md-7">
                <div class="input-group">
                    <input type="text" id="spt_competitor_url" class="form-control" placeholder="https://www.tienda-competencia.com/producto" value="{$competitor_url|escape:'html':'UTF-8'}" />
                    <div class="input-group-append input-group-btn">
                        <button type="button" id="spt_btn_scan" class="btn btn-primary">
                            <i class="icon-search process-icon-refresh"></i> Guardar y Analizar Ahora
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <hr>

        <div id="spt_results_area" class="{if $last_price == 0}d-none hidden{/if}">
            <div class="row text-center mt-3">
                <div class="col-md-4">
                    <div class="card bg-light p-3">
                        <h5 class="text-muted">Mi Precio (PVP)</h5>
                        <h3 id="spt_my_price">{$my_price|number_format:2:',':'.'} €</h3>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card bg-light p-3">
                        <h5 class="text-muted">Precio Competencia</h5>
                        <h3 id="spt_comp_price" class="text-info">
                            {if $last_price > 0}{$last_price|number_format:2:',':'.'} €{else}--{/if}
                        </h3>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card bg-light p-3" id="spt_diff_card">
                        <h5 class="text-muted">Diferencia</h5>
                        <h3 id="spt_diff_text" class="{if $diff > 0}text-danger{elseif $diff < 0}text-success{else}text-warning{/if}">
                            {if $diff > 0}
                                +{$diff|abs|number_format:2:',':'.'} € (Más Caro)
                            {elseif $diff < 0}
                                -{$diff|abs|number_format:2:',':'.'} € (Más Barato)
                            {else}
                                Mismo precio
                            {/if}
                        </h3>
                    </div>
                </div>
            </div>
            <div class="text-center mt-2 text-muted small">
                Último escaneo: <span id="spt_last_scan">{if $last_scan}{$last_scan|date_format:"%d/%m/%Y %H:%M:%S"}{/if}</span>
            </div>
        </div>

        <div id="spt_loader" class="text-center d-none hidden mt-4">
            <p><i class="icon-refresh icon-spin icon-fw"></i> Conectando con la tienda y analizando código fuente...</p>
        </div>
        
        <div id="spt_error_msg" class="alert alert-danger d-none hidden mt-3"></div>
    </div>
</div>

<script type="text/javascript">
document.addEventListener("DOMContentLoaded", function() {
    var btnScan = document.getElementById('spt_btn_scan');
    
    if(btnScan) {
        btnScan.addEventListener('click', function(e) {
            e.preventDefault();
            
            var url = document.getElementById('spt_competitor_url').value;
            if(!url) {
                alert("Por favor, introduce una URL.");
                return;
            }

            // Resetear interfaz: Ocultar alertas, mostrar loader
            document.getElementById('spt_error_msg').classList.add('d-none', 'hidden');
            document.getElementById('spt_loader').classList.remove('d-none', 'hidden');
            document.getElementById('spt_results_area').classList.add('d-none', 'hidden');
            btnScan.disabled = true;

            // Preparar variables para la petición AJAX asíncrona a Prestashop
            var formData = new FormData();
            formData.append('ajax', 1);
            formData.append('action', 'saveAndScan');
            formData.append('id_product', '{$id_product|intval}');
            formData.append('competitor_url', url);

            // Llamar al AdminSmartPriceTrackerAjaxController
            fetch('{$ajax_link|escape:'javascript':'UTF-8'}', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                btnScan.disabled = false;
                document.getElementById('spt_loader').classList.add('d-none', 'hidden');

                if (data.success) {
                    // Mostrar resultados
                    document.getElementById('spt_results_area').classList.remove('d-none', 'hidden');
                    
                    document.getElementById('spt_my_price').innerText = data.my_price.replace('.', ',') + ' €';
                    document.getElementById('spt_comp_price').innerText = data.competitor_price.replace('.', ',') + ' €';
                    document.getElementById('spt_last_scan').innerText = data.last_scan;

                    // Formatear el texto de diferencia
                    var diffText = document.getElementById('spt_diff_text');
                    var diffRaw = parseFloat(data.diff_raw);
                    var diffAbs = Math.abs(diffRaw).toFixed(2).replace('.', ',');

                    diffText.className = ''; // reset classes
                    if (diffRaw > 0) {
                        diffText.classList.add('text-danger');
                        diffText.innerText = '+' + diffAbs + ' € (Más Caro)';
                    } else if (diffRaw < 0) {
                        diffText.classList.add('text-success');
                        diffText.innerText = '-' + diffAbs + ' € (Más Barato)';
                    } else {
                        diffText.classList.add('text-warning');
                        diffText.innerText = 'Mismo precio';
                    }
                } else {
                    // Mostrar mensaje de error del servidor
                    var errorDiv = document.getElementById('spt_error_msg');
                    errorDiv.innerText = data.error;
                    errorDiv.classList.remove('d-none', 'hidden');
                    document.getElementById('spt_results_area').classList.remove('d-none', 'hidden'); // Mostrar anterior si existía
                }
            })
            .catch(error => {
                // Fallo grave de red o de PHP
                btnScan.disabled = false;
                document.getElementById('spt_loader').classList.add('d-none', 'hidden');
                var errorDiv = document.getElementById('spt_error_msg');
                errorDiv.innerText = "Error de conexión con el servidor. Activa el modo Debug de PrestaShop para ver los detalles.";
                errorDiv.classList.remove('d-none', 'hidden');
            });
        });
    }
});
</script>