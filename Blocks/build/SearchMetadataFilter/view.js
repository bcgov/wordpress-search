/******/ (() => { // webpackBootstrap
/******/ 	"use strict";
/******/ 	var __webpack_modules__ = ({

/***/ "./src/SearchMetadataFilter/view.scss":
/*!********************************************!*\
  !*** ./src/SearchMetadataFilter/view.scss ***!
  \********************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
// extracted by mini-css-extract-plugin


/***/ })

/******/ 	});
/************************************************************************/
/******/ 	// The module cache
/******/ 	var __webpack_module_cache__ = {};
/******/ 	
/******/ 	// The require function
/******/ 	function __webpack_require__(moduleId) {
/******/ 		// Check if module is in cache
/******/ 		var cachedModule = __webpack_module_cache__[moduleId];
/******/ 		if (cachedModule !== undefined) {
/******/ 			return cachedModule.exports;
/******/ 		}
/******/ 		// Create a new module (and put it into the cache)
/******/ 		var module = __webpack_module_cache__[moduleId] = {
/******/ 			// no module.id needed
/******/ 			// no module.loaded needed
/******/ 			exports: {}
/******/ 		};
/******/ 	
/******/ 		// Execute the module function
/******/ 		__webpack_modules__[moduleId](module, module.exports, __webpack_require__);
/******/ 	
/******/ 		// Return the exports of the module
/******/ 		return module.exports;
/******/ 	}
/******/ 	
/************************************************************************/
/******/ 	/* webpack/runtime/make namespace object */
/******/ 	(() => {
/******/ 		// define __esModule on exports
/******/ 		__webpack_require__.r = (exports) => {
/******/ 			if(typeof Symbol !== 'undefined' && Symbol.toStringTag) {
/******/ 				Object.defineProperty(exports, Symbol.toStringTag, { value: 'Module' });
/******/ 			}
/******/ 			Object.defineProperty(exports, '__esModule', { value: true });
/******/ 		};
/******/ 	})();
/******/ 	
/************************************************************************/
var __webpack_exports__ = {};
// This entry needs to be wrapped in an IIFE because it needs to be isolated against other modules in the chunk.
(() => {
/*!******************************************!*\
  !*** ./src/SearchMetadataFilter/view.js ***!
  \******************************************/
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _view_scss__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./view.scss */ "./src/SearchMetadataFilter/view.scss");
/**
 * Frontend Styles
 * Import the styles that will be applied to the search Metadata filter block on the frontend
 */



/**
 * Search Metadata Filter Block Frontend JavaScript
 *
 * This script runs on the frontend when the search Metadata filter block is rendered.
 *
 * Current features:
 * - Imports and applies frontend styles
 * - Provides toggle functionality for collapsible filter sections
 *
 */

// Toggle function for metadata filter
window.toggleMetadataFilter = function (header) {
  const content = header.nextElementSibling;
  const toggle = header.querySelector('.metadata-filter__toggle');
  if (content.classList.contains('collapsed')) {
    content.classList.remove('collapsed');
    toggle.classList.remove('collapsed');
  } else {
    content.classList.add('collapsed');
    toggle.classList.add('collapsed');
  }
};
})();

/******/ })()
;
//# sourceMappingURL=view.js.map