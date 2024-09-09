(function (Drupal, once) {
  Drupal.behaviors.biggerPictureInit = {
    attach: function (context, settings) {
      const bp = BiggerPicture({
        target: document.body,
      });

      const groupedLinks = {};

      let links = once('bigger-picture-init', 'a[data-lightbox-group]');
      links.forEach((element) => {
        let group = element.getAttribute('data-lightbox-group');

        if (!groupedLinks[group]) {
          groupedLinks[group] = [];
        }

        groupedLinks[group].push(element);
      });

      Object.values(groupedLinks).forEach((linksGroup) => {
        linksGroup.forEach((link) => {
          link.addEventListener("click", (e) => openGallery(bp, e, linksGroup));
        });
      });

      function openGallery(bp, e, imageLinks) {
        e.preventDefault();
        bp.open({
          items: imageLinks,
          el: e.currentTarget,
        });
      }
    }
  };
})(Drupal, once);
