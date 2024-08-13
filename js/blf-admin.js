jQuery(document).ready(function($) {
    var cleaningInProgress = false;

    function startCleaning() {
        if (cleaningInProgress) {
            return;
        }

        cleaningInProgress = true;
        $('#blf-status').html('Iniciando limpieza...');
        $('#blf-start-cleaning').hide();
        $('#blf-cancel-cleaning').show();

        var internalOnly = $('#blf-internal-only').is(':checked');

        // Enviar solicitud AJAX para iniciar la limpieza
        $.ajax({
            url: blf_ajax.ajax_url,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'blf_start_cleaning',
                nonce: blf_ajax.nonce,
                internal_only: internalOnly
            },
            success: function(response) {
                if (response.success) {
                    var result = response.data;
                    var statusMessage = 'Limpieza completada.<br>';
                    statusMessage += 'Enlaces totales: ' + result.link_count + '<br>';
                    statusMessage += 'Enlaces rotos: ' + result.broken_link_count + '<br>';
                    statusMessage += 'Enlaces limpiados: ' + result.cleaned_count + '<br>';
                    if (result.last_cleaned_url) {
                        statusMessage += 'Última URL limpiada: ' + result.last_cleaned_url + '<br>';
                    }
                                        // Add new fields to status message
                    statusMessage += 'Enlaces internos: ' + result.internal_links_count + '<br>';
                    statusMessage += 'Enlaces externos: ' + result.external_links_count + '<br>';
                    $('#blf-status').html(statusMessage);
                    cleaningInProgress = false;
                    $('#blf-start-cleaning').show();
                    $('#blf-cancel-cleaning').hide();
                } else {
                    cleaningInProgress = false;
                    $('#blf-status').html('Error durante la limpieza: ' + response.data);
                    $('#blf-start-cleaning').show();
                    $('#blf-cancel-cleaning').hide();
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                cleaningInProgress = false;
                $('#blf-status').html('Error durante la limpieza: ' + textStatus + ' - ' + errorThrown);
                $('#blf-start-cleaning').show();
                $('#blf-cancel-cleaning').hide();
            }
        });
    }

    $('#blf-start-cleaning').on('click', startCleaning);

    $('#blf-cancel-cleaning').on('click', function() {
        if (!cleaningInProgress) {
            return;
        }

        cleaningInProgress = false;
        $('#blf-start-cleaning').show();
        $('#blf-cancel-cleaning').hide();
        $('#blf-status').html('Limpieza cancelada.');

        // Enviar solicitud AJAX para cancelar la limpieza
        $.ajax({
            url: blf_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'blf_cancel_cleaning',
                nonce: blf_ajax.nonce
            },
            success: function(response) {
                $('#blf-status').html('Limpieza cancelada exitosamente.');
            },
            error: function(jqXHR, textStatus, errorThrown) {
                $('#blf-status').html('Error al cancelar la limpieza: ' + textStatus + ' - ' + errorThrown);
            }
        });
    });

    $('#blf-clean-specific-post').on('click', function() {
        var postId = $('#blf-post-id').val();
        if (!postId) {
            $('#blf-specific-post-status').html('Por favor, ingrese un ID de post válido.');
            return;
        }
        
        var internalOnly = $('#blf-internal-only').is(':checked');

        $('#blf-specific-post-status').html('Limpiando post...');

        $.ajax({
            url: blf_ajax.ajax_url,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'blf_clean_specific_post',
                nonce: blf_ajax.nonce,
                post_id: postId,
                internal_only: internalOnly
            },
            success: function(response) {
                if (response.success) {
                    var result = response.data;
                    var statusMessage = 'Post ID: ' + result.post_id + '<br>';
                    statusMessage += 'Enlaces totales: ' + result.link_count + '<br>';
                    statusMessage += 'Enlaces rotos: ' + result.broken_link_count + '<br>';
                    statusMessage += 'Enlaces limpiados: ' + result.cleaned_count + '<br>';
                    if (result.last_cleaned_url) {
                        statusMessage += 'Última URL limpiada: ' + result.last_cleaned_url + '<br>';
                    }
                    // Add new fields to status message
                    statusMessage += 'Enlaces internos: ' + result.internal_links_count + '<br>';
                    statusMessage += 'Enlaces externos: ' + result.external_links_count + '<br>';
                    $('#blf-specific-post-status').html(statusMessage);
                } else {
                    $('#blf-specific-post-status').html('Error durante la limpieza: ' + response.data);
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                $('#blf-specific-post-status').html('Error durante la limpieza: ' + textStatus + ' - ' + errorThrown);
            }
        });
    });
});