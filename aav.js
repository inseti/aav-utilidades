/**
 * AAV Utilidades - JavaScript principal
 * Version: 3.0
 * Author: Digitis
 */

(function() {
    'use strict';
    
    // Variables globales
    let headings = [];
    let tocLinks = [];
    let isScrolling = false;
    let modal = null;
    
    // Esperar a que el DOM esté listo
    document.addEventListener('DOMContentLoaded', function() {
        
        // Toggle del TOC en mobile
        initMobileTOC();
        
        // Ajustar sticky position si hay admin bar
        adjustStickyPosition();
        
        // Inicializar navegación suave y scroll spy
        initSmoothScroll();
        initScrollSpy();
        
        // Inicializar sistema de glosario
        initGlossaryTerms();
        
        // Inicializar acordeón de referencias
        initReferencesAccordion();
        
        // Inicializar smooth scroll para footnotes
        initFootnotesScroll();
        
        // Inicializar modal de contacto de expertos
        initExpertContactModal();
        
        // Inicializar toggle de expertos - FALTABA ESTA LLAMADA
        initExpertsToggle();
        
    });
    
    /**
     * Inicializar toggle de expertos - VERSIÓN QUE SÍ FUNCIONABA
     */
    function initExpertsToggle() {
        const expertsToggle = document.querySelector('.aav-experts-toggle');
        const expertsList = document.querySelector('.aav-experts-list');
        
        if (!expertsToggle || !expertsList) return;
        
        expertsToggle.addEventListener('click', function(e) {
            e.preventDefault();
            
            const isExpanded = this.getAttribute('aria-expanded') === 'true';
            
            // Toggle aria-expanded
            this.setAttribute('aria-expanded', !isExpanded);
            
            // Toggle clase show en la lista
            if (!isExpanded) {
                expertsList.classList.add('show');
            } else {
                expertsList.classList.remove('show');
            }
        });
    }
    
    /**
     * Inicializar TOC mobile y toggles de submenús
     */
    function initMobileTOC() {
        const tocToggle = document.querySelector('.aav-toc-toggle');
        if (tocToggle) {
            tocToggle.addEventListener('click', function() {
                const toc = this.closest('.aav-toc');
                const isExpanded = this.getAttribute('aria-expanded') === 'true';
                
                // Toggle estado
                this.setAttribute('aria-expanded', !isExpanded);
                toc.classList.toggle('expanded');
            });
        }
        
        // Toggles para items con hijos (desktop y mobile)
        const tocItemsWithChildren = document.querySelectorAll('.aav-toc-list > li.has-children > a');
        tocItemsWithChildren.forEach(link => {
            link.addEventListener('click', function(e) {
                // Prevenir navegación por defecto
                e.preventDefault();
                
                const parentLi = this.parentElement;
                const wasExpanded = parentLi.classList.contains('expanded');
                
                // Toggle estado
                parentLi.classList.toggle('expanded');
                
                // Si estaba expandido antes del click, navegar
                if (wasExpanded) {
                    const targetId = this.getAttribute('href').substring(1);
                    const target = document.getElementById(targetId);
                    if (target) {
                        smoothScrollToElement(target);
                    }
                }
            });
        });
        
        // Cerrar TOC al hacer click en un enlace de subitem (solo mobile)
        if (window.innerWidth <= 767) {
            const tocSubLinks = document.querySelectorAll('.aav-toc-list ol a');
            tocSubLinks.forEach(link => {
                link.addEventListener('click', function() {
                    const toc = this.closest('.aav-toc');
                    const toggle = toc.querySelector('.aav-toc-toggle');
                    
                    // Cerrar TOC
                    if (toggle) {
                        toggle.setAttribute('aria-expanded', 'false');
                        toc.classList.remove('expanded');
                    }
                });
            });
        }
        
        // NO expandir items por defecto en desktop (eliminado)
    }
    
    /**
     * Función auxiliar para smooth scroll
     */
    function smoothScrollToElement(target) {
        isScrolling = true;
        
        const adminBar = document.getElementById('wpadminbar');
        const offset = adminBar ? adminBar.offsetHeight + 20 : 20;
        const targetPosition = target.getBoundingClientRect().top + window.pageYOffset - offset;
        
        window.scrollTo({
            top: targetPosition,
            behavior: 'smooth'
        });
        
        if (history.pushState) {
            history.pushState(null, null, '#' + target.id);
        }
        
        setTimeout(() => {
            isScrolling = false;
        }, 1000);
    }
    
    /**
     * Ajustar posición sticky si hay admin bar
     */
    function adjustStickyPosition() {
        const adminBar = document.getElementById('wpadminbar');
        const sidebar = document.querySelector('.aav-sidebar');
        
        if (adminBar && sidebar && window.innerWidth > 767) {
            const adminBarHeight = adminBar.offsetHeight;
            sidebar.style.top = (adminBarHeight + 32) + 'px';
        }
    }
    
    /**
     * Inicializar navegación suave
     */
    function initSmoothScroll() {
        const links = document.querySelectorAll('.aav-toc-list a[href^="#"]');
        
        links.forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                
                const targetId = this.getAttribute('href').substring(1);
                const target = document.getElementById(targetId);
                
                if (target) {
                    smoothScrollToElement(target);
                }
            });
        });
    }
    
    /**
     * Inicializar scroll spy
     */
    function initScrollSpy() {
        // Obtener todos los headings con ID
        const headingElements = document.querySelectorAll('.aav-post-content h2[id], .aav-post-content h3[id]');
        
        headings = Array.from(headingElements).map(heading => ({
            id: heading.id,
            offsetTop: heading.offsetTop,
            element: heading
        }));
        
        // Obtener todos los enlaces del TOC
        tocLinks = Array.from(document.querySelectorAll('.aav-toc-list a'));
        
        if (headings.length === 0 || tocLinks.length === 0) {
            return;
        }
        
        // Escuchar scroll
        let ticking = false;
        window.addEventListener('scroll', () => {
            if (!ticking && !isScrolling) {
                window.requestAnimationFrame(() => {
                    updateActiveSection();
                    ticking = false;
                });
                ticking = true;
            }
        });
        
        // Actualizar sección activa inicialmente
        updateActiveSection();
    }
    
    /**
     * Actualizar sección activa en el TOC
     */
    function updateActiveSection() {
        const scrollPosition = window.pageYOffset;
        const windowHeight = window.innerHeight;
        const documentHeight = document.documentElement.scrollHeight;
        
        // Offset para considerar una sección como activa
        const offset = 100;
        
        let activeHeading = null;
        
        // Si estamos al final del documento, activar la última sección
        if (scrollPosition + windowHeight >= documentHeight - 50) {
            activeHeading = headings[headings.length - 1];
        } else {
            // Encontrar el heading activo
            for (let i = headings.length - 1; i >= 0; i--) {
                if (scrollPosition >= headings[i].offsetTop - offset) {
                    activeHeading = headings[i];
                    break;
                }
            }
        }
        
        // Primero quitar todas las clases active
        tocLinks.forEach(link => {
            link.classList.remove('active');
        });
        
        // Luego añadir active al correcto
        if (activeHeading) {
            tocLinks.forEach(link => {
                if (link.getAttribute('href') === '#' + activeHeading.id) {
                    link.classList.add('active');
                    
                    // También activar el padre si es un H3
                    const parentLi = link.closest('ol')?.closest('li');
                    if (parentLi) {
                        const parentLink = parentLi.querySelector(':scope > a');
                        if (parentLink) {
                            parentLink.classList.add('active');
                        }
                    }
                }
            });
        }
    }
    
    /**
     * Inicializar términos del glosario
     */
    function initGlossaryTerms() {
        const glossaryTerms = document.querySelectorAll('.aav-glossary-term');
        
        glossaryTerms.forEach(term => {
            // Click para abrir modal
            term.addEventListener('click', function() {
                openGlossaryModal(this);
            });
            
            // Enter para accesibilidad
            term.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    openGlossaryModal(this);
                }
            });
        });
        
        // Crear modal si no existe
        if (!document.getElementById('aav-glossary-modal')) {
            createGlossaryModal();
        }
    }
    
    /**
     * Crear estructura del modal
     */
    function createGlossaryModal() {
        const modalHTML = `
            <div id="aav-glossary-modal" class="aav-modal-overlay">
                <div class="aav-modal">
                    <div class="aav-modal-header">
                        <h3 class="aav-modal-title"></h3>
                        <button class="aav-modal-close" aria-label="Cerrar">&times;</button>
                    </div>
                    <div class="aav-modal-body">
                        <div class="aav-modal-loading">Cargando...</div>
                    </div>
                </div>
            </div>
        `;
        
        document.body.insertAdjacentHTML('beforeend', modalHTML);
        
        modal = document.getElementById('aav-glossary-modal');
        
        // Eventos para cerrar
        modal.querySelector('.aav-modal-close').addEventListener('click', closeGlossaryModal);
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                closeGlossaryModal();
            }
        });
        
        // ESC para cerrar
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && modal.classList.contains('active')) {
                closeGlossaryModal();
            }
        });
    }
    
    /**
     * Abrir modal del glosario
     */
    function openGlossaryModal(termElement) {
        const term = termElement.dataset.term;
        const termId = termElement.dataset.id;
        
        if (!modal) {
            createGlossaryModal();
        }
        
        // Mostrar modal
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
        
        // Cargar contenido
        loadGlossaryContent(term, termId);
    }
    
    /**
     * Cerrar modal del glosario
     */
    function closeGlossaryModal() {
        modal.classList.remove('active');
        document.body.style.overflow = '';
    }
    
    /**
     * Cargar contenido del glosario via AJAX
     */
    function loadGlossaryContent(term, termId) {
        const modalBody = modal.querySelector('.aav-modal-body');
        const modalTitle = modal.querySelector('.aav-modal-title');
        
        // Mostrar loading
        modalBody.innerHTML = '<div class="aav-modal-loading">Cargando...</div>';
        
        // Hacer petición AJAX
        const formData = new FormData();
        formData.append('action', 'aav_get_glossary_term');
        formData.append('term', term);
        formData.append('id', termId);
        formData.append('nonce', aav_ajax.nonce);
        
        fetch(aav_ajax.url, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const termData = data.data;
                
                // Actualizar título
                modalTitle.textContent = termData.term || term;
                
                // Construir contenido
                let content = '<div class="aav-glossary-definition">';
                content += termData.definition_full || termData.definition_short || 'Sin definición disponible.';
                content += '</div>';
                
                // Añadir guías relacionadas si existen
                if (termData.related_guides) {
                    content += '<div class="aav-glossary-related">';
                    content += '<h4>Guías relacionadas:</h4>';
                    content += '<ul>';
                    
                    // Aquí procesarías las guías relacionadas
                    // Por ahora ejemplo simple
                    content += '<li><a href="#">Guía sobre ' + termData.term + '</a></li>';
                    
                    content += '</ul>';
                    content += '</div>';
                }
                
                modalBody.innerHTML = content;
            } else {
                modalBody.innerHTML = '<p>No se pudo cargar la información del término.</p>';
            }
        })
        .catch(error => {
            modalBody.innerHTML = '<p>Error al cargar el contenido.</p>';
            console.error('Error:', error);
        });
    }
    
    /**
     * Inicializar acordeón de referencias
     */
    function initReferencesAccordion() {
        const referencesToggle = document.querySelector('.aav-references-toggle');
        if (!referencesToggle) return;
        
        const referencesContent = document.querySelector('.aav-references-content');
        
        referencesToggle.addEventListener('click', function() {
            const isExpanded = this.getAttribute('aria-expanded') === 'true';
            
            // Toggle estado
            this.setAttribute('aria-expanded', !isExpanded);
            referencesContent.classList.toggle('expanded');
        });
    }
    
    /**
     * Inicializar smooth scroll para footnotes
     */
    function initFootnotesScroll() {
        // Enlaces a footnotes
        const footnoteLinks = document.querySelectorAll('.aav-footnote-ref a');
        footnoteLinks.forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                
                const targetId = this.getAttribute('href').substring(1);
                const target = document.getElementById(targetId);
                
                if (target) {
                    // Expandir referencias si está colapsado
                    const referencesContent = document.querySelector('.aav-references-content');
                    const referencesToggle = document.querySelector('.aav-references-toggle');
                    
                    if (referencesContent && !referencesContent.classList.contains('expanded')) {
                        referencesToggle.setAttribute('aria-expanded', 'true');
                        referencesContent.classList.add('expanded');
                    }
                    
                    // Scroll suave
                    setTimeout(() => {
                        smoothScrollToElement(target);
                    }, 300);
                }
            });
        });
        
        // Enlaces de retorno
        const backLinks = document.querySelectorAll('.aav-back-link');
        backLinks.forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                
                const targetId = this.getAttribute('href').substring(1);
                const target = document.getElementById(targetId);
                
                if (target) {
                    smoothScrollToElement(target);
                }
            });
        });
    }
    
    /**
     * Inicializar modal de contacto de expertos
     */
    function initExpertContactModal() {
        // Crear modal si no existe
        if (!document.getElementById('aav-contact-modal')) {
            createContactModal();
        }
        
        // Añadir event listeners a todos los enlaces de contacto
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('aav-expert-contact')) {
                e.preventDefault();
                const expertName = e.target.getAttribute('data-expert-name');
                const expertId = e.target.getAttribute('data-expert-id');
                openContactModal(expertName, expertId);
            }
        });
    }
    
    /**
     * Crear estructura del modal de contacto
     */
    function createContactModal() {
        const modalHTML = `
            <div id="aav-contact-modal" class="aav-contact-modal-overlay">
                <div class="aav-contact-modal">
                    <button class="aav-contact-modal-close" aria-label="Cerrar">&times;</button>
                    <div class="aav-contact-modal-header">
                        <h3 class="aav-contact-modal-title">Contactar con el experto</h3>
                        <p class="aav-contact-modal-subtitle">Completa el formulario y <span class="expert-name"></span> responderá tus dudas</p>
                    </div>
                    <div class="aav-contact-modal-body">
                        <div class="aav-modal-loading">Cargando formulario...</div>
                    </div>
                </div>
            </div>
        `;
        
        document.body.insertAdjacentHTML('beforeend', modalHTML);
        
        const contactModal = document.getElementById('aav-contact-modal');
        
        // Eventos para cerrar
        contactModal.querySelector('.aav-contact-modal-close').addEventListener('click', closeContactModal);
        contactModal.addEventListener('click', function(e) {
            if (e.target === contactModal) {
                closeContactModal();
            }
        });
        
        // ESC para cerrar
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && contactModal.classList.contains('active')) {
                closeContactModal();
            }
        });
    }
    
    /**
     * Abrir modal de contacto
     */
    function openContactModal(expertName, expertId) {
        const contactModal = document.getElementById('aav-contact-modal');
        if (!contactModal) {
            createContactModal();
        }
        
        // Actualizar nombre del experto
        const expertNameSpan = contactModal.querySelector('.expert-name');
        if (expertNameSpan) {
            expertNameSpan.textContent = expertName;
        }
        
        // Mostrar modal
        contactModal.classList.add('active');
        document.body.style.overflow = 'hidden';
        
        // Cargar formulario
        loadContactForm(expertId, expertName);
    }
    
    /**
     * Cerrar modal de contacto
     */
    function closeContactModal() {
        const contactModal = document.getElementById('aav-contact-modal');
        if (contactModal) {
            contactModal.classList.remove('active');
            document.body.style.overflow = '';
        }
    }
    
    /**
     * Cargar formulario de contacto
     */
    function loadContactForm(expertId, expertName) {
        const modalBody = document.querySelector('#aav-contact-modal .aav-contact-modal-body');
        
        // Obtener email del experto del data attribute
        const expertLink = document.querySelector(`.aav-expert-contact[data-expert-id="${expertId}"]`);
        const expertEmail = expertLink ? expertLink.getAttribute('data-expert-email') : '';
        
        // Obtener URL actual
        const currentPage = window.location.href;
        
        // Mostrar loading
        modalBody.innerHTML = '<div class="aav-modal-loading">Cargando formulario...</div>';
        
        // Hacer petición AJAX para cargar el formulario
        const formData = new FormData();
        formData.append('action', 'aav_load_contact_form');
        formData.append('expert_id', expertId);
        formData.append('expert_name', expertName);
        formData.append('expert_email', expertEmail);
        formData.append('current_page', currentPage);
        formData.append('nonce', aav_ajax.nonce);
        
        fetch(aav_ajax.url, {
            method: 'POST',
            body: formData
        })
        .then(response => response.text())
        .then(html => {
            modalBody.innerHTML = html;
            
            // Reinicializar Gravity Forms si está disponible
            if (window.jQuery && window.gformInitSpinner) {
                // Obtener el ID del formulario
                const formId = aav_ajax.contact_form_id || 5;
                
                // Inicializar los scripts de Gravity Forms
                window.jQuery(document).trigger('gform_post_render', [formId, 1]);
                
                // Si hay datepickers, inicializarlos
                if (window.gformInitDatepicker) {
                    window.gformInitDatepicker();
                }
            }
            
            // Manejar el envío exitoso del formulario
            if (window.jQuery) {
                window.jQuery(document).on('gform_confirmation_loaded', function(event, formId) {
                    // Cerrar el modal después de 3 segundos
                    setTimeout(() => {
                        closeContactModal();
                    }, 3000);
                });
            }
        })
        .catch(error => {
            modalBody.innerHTML = `
                <div style="text-align: center; padding: 2rem;">
                    <p style="color: #dc3545;">Error al cargar el formulario.</p>
                    <p style="margin-top: 1rem;">Por favor, intenta de nuevo más tarde.</p>
                    <button class="aav-donation-button" style="margin-top: 1rem;" onclick="closeContactModal()">Cerrar</button>
                </div>
            `;
            console.error('Error:', error);
        });
    }
    
    // Re-ajustar en resize
    window.addEventListener('resize', function() {
        adjustStickyPosition();
        
        // Recalcular posiciones de headings
        if (headings.length > 0) {
            headings.forEach(heading => {
                heading.offsetTop = heading.element.offsetTop;
            });
        }
    });
    
})();