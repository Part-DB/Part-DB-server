/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2026 Jan Böhmer (https://github.com/jbtronics)
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU Affero General Public License as published
 *  by the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU Affero General Public License for more details.
 *
 *  You should have received a copy of the GNU Affero General Public License
 *  along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

import occtimportjs from "occt-import-js";
//Webpack emits the (rather large) WASM binary as a separate asset and gives us its public URL here,
//so it is only downloaded when a boundary representation file is actually opened.
import wasmUrl from "occt-import-js/dist/occt-import-js.wasm";

/**
 * Tessellates boundary representation CAD files (STEP, IGES, BREP) into triangle meshes using the
 * OpenCascade based occt-import-js. This runs in a worker, as tessellating a big assembly takes
 * seconds and would otherwise freeze the whole page.
 */

let occtPromise = null;

self.onmessage = async (event) => {
    const {format, buffer} = event.data;

    try {
        //Instantiating the WASM module is expensive, so keep it around for subsequent messages
        occtPromise ??= occtimportjs({locateFile: () => wasmUrl});
        const occt = await occtPromise;

        const result = occt.ReadFile(format, new Uint8Array(buffer), null);
        if (!result || !result.success) {
            throw new Error("occt-import-js was not able to read the file");
        }

        self.postMessage({success: true, meshes: result.meshes});
    } catch (error) {
        self.postMessage({success: false, error: error?.message ?? String(error)});
    }
};
