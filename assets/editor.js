(function (blocks, element, components) {
    'use strict';
    blocks.registerBlockType('kic/contact-form', {
        apiVersion: 2,
        title: 'KIC Contact Form',
        icon: 'email',
        category: 'widgets',
        attributes: {
            formId: { type: 'string' },
            fields: { type: 'array', default: [] },
            submitText: { type: 'string', default: 'Submit' }
            ,styleId: { type: 'string' }
            ,buttonStyleId: { type: 'string' }
        },
        edit: function (props) {
            var count = (props.attributes.fields || []).length;
            return element.createElement(components.Placeholder, {
                icon: 'email',
                label: 'KIC Contact Form'
            }, 'Form “' + (props.attributes.formId || 'contact') + '” — ' + count + ' fields');
        },
        save: function () { return null; }
    });
}(window.wp.blocks, window.wp.element, window.wp.components));
