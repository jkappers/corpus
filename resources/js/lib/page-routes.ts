import * as boardsApi from '@/routes/api/v1/boards';
import * as boardVersions from '@/routes/api/v1/boards/versions';
import * as pagesApi from '@/routes/api/v1/pages';
import * as pageVersions from '@/routes/api/v1/pages/versions';
import { show as showBoard } from '@/routes/boards';
import { show as showPage } from '@/routes/pages';
import type { PageType } from '@/types/page';

/**
 * Pages and boards share listings in the UI but are separate resources on the server:
 * a page id is not found under /boards, and a board id is not found under /pages.
 * Look up the routes for a row by its type instead of assuming /pages.
 */
export function routesFor(type: PageType) {
    return type === 'board'
        ? { show: showBoard, api: boardsApi, versions: boardVersions }
        : { show: showPage, api: pagesApi, versions: pageVersions };
}
