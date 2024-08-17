$(document).ready(function() {
    var cleaningInProgress = false;

    function startCleaning() {
        if (cleaningInProgress) {
            return;
        }

        cleaningInProgress = true;
        $('#blf-start-cleaning').hide();
        $('#blf-cancel-cleaning').show();
        $('#blf-status').html('Iniciando limpieza...');

        $.ajax({
            url: blf_ajax.ajax_url,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'blf_start_cleaning',
                nonce: blf_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    var result = response.data;
                    var statusMessage = 'Limpieza completada exitosamente.<br>';
                    statusMessage += 'Enlaces totales: ' + result.link_count + '<br>';
                    statusMessage += 'Enlaces rotos: ' + result.broken_link_count + '<br>';
                    statusMessage += 'Enlaces limpiados: ' + result.cleaned_count + '<br>';
                    if (result.last_cleaned_url) {
                        statusMessage += 'Última URL limpiada: ' + result.last_cleaned_url + '<br>';
                    }
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

    // Manejador para limpiar posts específicos
    $('#blf-clean-specific-post').on('click', function() {
        var inputValue = $('#blf-post-input').val();
        if (!inputValue) {
            $('#blf-specific-post-status').html('Por favor, ingrese uno o más IDs o URLs de post válidos.');
            return;
        }

        var postIds = inputValue.split(/\n/); // Separar por nueva línea
        var internalOnly = $('#blf-internal-only').is(':checked');

        $('#blf-specific-post-status').html('Limpiando posts...');
        var urlPattern = /^(http|https):\/\/[^\s/$.?#].[^\s]*$/i;

        postIds.forEach(function(postId) {
            if (!postId.trim()) {
                return;
            }

            var isUrl = urlPattern.test(postId);
            var postIdValue = isUrl ? postId : parseInt(postId, 10);

            if (isNaN(postIdValue) && !isUrl) {
                $('#blf-specific-post-status').append('<p>ID o URL no válido: ' + postId + '</p>');
                return;
            }

            $.ajax({
                url: blf_ajax.ajax_url,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'blf_clean_specific_post',
                    nonce: blf_ajax.nonce,
                    post_id: postIdValue,
                    internal_only: internalOnly
                },
                success: function(response) {
                    console.log("response:"+response); // <-- Añadir esta línea para ver la respuesta completa.
                    try {
                        var result = JSON.parse(response);
                        if (!result) {
                            $('#blf-specific-post-status').append('<p>Formato de respuesta no válido para el post ' + postIdValue + '.</p>');
                            return;
                        }
                        var statusMessage = 'Post ID/URL: ' + result.post_id + '<br>';
                        statusMessage += 'Enlaces totales: ' + result.link_count + '<br>';
                        statusMessage += 'Enlaces rotos: ' + result.broken_link_count + '<br>';
                        statusMessage += 'Enlaces limpiados: ' + result.cleaned_count + '<br>';
                        if (result.last_cleaned_url) {
                            statusMessage += 'Última URL limpiada: ' + result.last_cleaned_url + '<br>';
                        }
                        // Add new fields to status message
                        statusMessage += 'Enlaces internos: ' + result.internal_links_count + '<br>';
                        statusMessage += 'Enlaces externos: ' + result.external_links_count + '<br>';
                        $('#blf-specific-post-status').append('<p>' + statusMessage + '</p>');
                    } catch (e) {
                        $('#blf-specific-post-status').append('<p>Error al parsear la respuesta del servidor para el post ' + postIdValue + ': ' + e.message + '</p>');
                    }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    $('#blf-specific-post-status').append('<p>Error durante la limpieza del post ' + postIdValue + ': ' + textStatus + ' - ' + errorThrown + '</p>');
                }
            });
        });
    });
});
