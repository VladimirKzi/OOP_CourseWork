'use strict';
(async () => {
  await Auth.restore();
  Nav.render();
  Router.init();
})();
