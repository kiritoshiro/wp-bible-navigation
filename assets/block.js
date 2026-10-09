/* "Bible navigation" block editor. No build step: plain wp.* globals. */
(function (wp) {
  'use strict';

  var el = wp.element.createElement;
  var be = wp.blockEditor;
  var c = wp.components;
  var text = window.bnavBlock || {title: 'Bible navigation', description: '', showEmpty: 'Show books without links'};

  wp.blocks.registerBlockType('bible-navigation/books', {
    apiVersion: 3,
    title: text.title,
    description: text.description,
    category: 'widgets',
    icon: 'book-alt',
    attributes: {showEmpty: {type: 'boolean', default: true}},
    supports: {align: ['wide', 'full'], html: false},
    edit: function (props) {
      return el('div', be.useBlockProps(),
        el(be.InspectorControls, null,
          el(c.PanelBody, null,
            el(c.ToggleControl, {
              label: text.showEmpty,
              checked: props.attributes.showEmpty,
              onChange: function (value) {
                props.setAttributes({showEmpty: value});
              }
            }))),
        el(wp.serverSideRender, {block: 'bible-navigation/books', attributes: props.attributes}));
    },
    save: function () {
      return null;
    }
  });
})(window.wp);
