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

import {Controller} from "@hotwired/stimulus";
import * as THREE from "three";
import {OrbitControls} from "three/addons/controls/OrbitControls.js";

import {trans} from "../../translator";
import "../../css/components/model_viewer.css";

/**
 * An interactive viewer for 3D model attachments.
 *
 * Mesh formats are parsed by the matching three.js loader, boundary representation (CAD) formats are
 * tessellated by occt-import-js in a worker first. Every loader is imported dynamically, so opening an
 * STL file does not download the FBX or the (multi megabyte) OpenCascade parser.
 *
 * The extensions handled here must stay in sync with Attachment::MODEL_EXTS.
 */

/**
 * Parsers for the mesh formats, mapping the raw file content to a three.js object.
 * @type {Object<string, function(ArrayBuffer): Promise<THREE.Object3D>>}
 */
const MESH_PARSERS = {
    stl: async (buffer) => {
        const {STLLoader} = await import("three/addons/loaders/STLLoader.js");
        return geometryToObject(new STLLoader().parse(buffer));
    },
    ply: async (buffer) => {
        const {PLYLoader} = await import("three/addons/loaders/PLYLoader.js");
        return geometryToObject(new PLYLoader().parse(buffer));
    },
    vtk: async (buffer) => {
        const {VTKLoader} = await import("three/addons/loaders/VTKLoader.js");
        return geometryToObject(new VTKLoader().parse(buffer));
    },
    obj: async (buffer) => {
        const {OBJLoader} = await import("three/addons/loaders/OBJLoader.js");
        //The companion .mtl file is not available here, so the objects keep the default material
        return new OBJLoader().parse(decodeText(buffer));
    },
    "3mf": async (buffer) => {
        const {ThreeMFLoader} = await import("three/addons/loaders/3MFLoader.js");
        return new ThreeMFLoader().parse(buffer);
    },
    "3ds": async (buffer) => {
        const {TDSLoader} = await import("three/addons/loaders/TDSLoader.js");
        return new TDSLoader().parse(buffer, "");
    },
    amf: async (buffer) => {
        const {AMFLoader} = await import("three/addons/loaders/AMFLoader.js");
        return new AMFLoader().parse(buffer);
    },
    dae: async (buffer) => {
        const {ColladaLoader} = await import("three/addons/loaders/ColladaLoader.js");
        const collada = new ColladaLoader().parse(decodeText(buffer), "");
        if (collada === null) {
            throw new Error("The COLLADA file could not be parsed");
        }
        return collada.scene;
    },
    fbx: async (buffer) => {
        const {FBXLoader} = await import("three/addons/loaders/FBXLoader.js");
        return new FBXLoader().parse(buffer, "");
    },
    wrl: async (buffer) => {
        const {VRMLLoader} = await import("three/addons/loaders/VRMLLoader.js");
        return new VRMLLoader().parse(decodeText(buffer), "");
    },
    gltf: async (buffer) => {
        const {GLTFLoader} = await import("three/addons/loaders/GLTFLoader.js");
        const loader = new GLTFLoader();
        //Only self contained files can be shown, as we have no directory to resolve external buffers against
        const gltf = await new Promise((resolve, reject) => loader.parse(buffer, "", resolve, reject));
        //The asset block is dropped when we keep only the scene, so carry it over for the metadata dialog
        gltf.scene.userData.gltfAsset = gltf.asset;
        return gltf.scene;
    },
};
MESH_PARSERS.glb = MESH_PARSERS.gltf;
MESH_PARSERS.vrml = MESH_PARSERS.wrl;

/**
 * The boundary representation formats and the format name occt-import-js expects for them.
 * @type {Object<string, string>}
 */
const OCCT_FORMATS = {
    step: "step",
    stp: "step",
    iges: "iges",
    igs: "iges",
    brep: "brep",
    brp: "brep",
};

/**
 * Formats whose specification (or universal convention) puts the up axis on Z, while three.js renders
 * with Y up. Models in these formats are rotated on load so that they are not shown lying on their side.
 * @type {string[]}
 */
const Z_UP_FORMATS = ["stl", "3mf", "amf", "step", "stp", "iges", "igs", "brep", "brp"];

/**
 * The camera directions (relative to the model centre) of the preset views. They are given in three.js
 * world space, where Y is up - which is also the up axis of a model after the Z_UP_FORMATS rotation, so
 * "top" is the top of the part no matter which format it came from.
 * @type {Object<string, number[]>}
 */
const VIEW_DIRECTIONS = {
    isometric: [1, 0.8, 1],
    front: [0, 0, 1],
    back: [0, 0, -1],
    left: [-1, 0, 0],
    right: [1, 0, 0],
    top: [0, 1, 0],
    bottom: [0, -1, 0],
};

/**
 * The unit the world coordinates of a model end up in for the formats where that is guaranteed - either
 * by the file format itself or because the three.js loader normalises it. Every other format is measured
 * in whatever the file used, which _detectUnit() tries to read back out of it.
 * @type {Object<string, string>}
 */
const FORMAT_UNITS = {
    //glTF is defined to be in meters
    gltf: "m",
    glb: "m",
    //ColladaLoader applies the file's own unit scale, which leaves the scene in meters
    dae: "m",
    //AMFLoader converts every unit it supports to millimeters
    amf: "mm",
};

/** The length units a STEP file can declare as an SI unit, by the prefix it uses. */
const STEP_SI_PREFIXES = {
    ".MICRO.": "µm",
    ".MILLI.": "mm",
    ".CENTI.": "cm",
    ".DECI.": "dm",
    ".KILO.": "km",
};

/** The unit assumed for the formats that do not store one. */
const FALLBACK_UNIT = "mm";

/** How close (in screen pixels) a corner has to be for a measurement point to snap onto it. */
const SNAP_DISTANCE_PX = 12;

/** A pointer that moved further than this between press and release was a drag (orbiting), not a click. */
const CLICK_TOLERANCE_PX = 4;

const MEASUREMENT_COLOR = 0xdc3545;

/** Meshes above this many triangles skip the volume/surface calculation, which walks every triangle. */
const MAX_TRIANGLES_FOR_VOLUME = 1500000;

const decodeText = (buffer) => new TextDecoder().decode(buffer);

/**
 * Turns the escapes of a STEP string into the characters they stand for: doubled quotes, and the \X2\
 * and \X\ sequences STEP uses for everything outside of ASCII (e.g. in an author or company name).
 */
const decodeStepString = (value) => value
    .replace(/\\X2\\([0-9A-Fa-f]+)\\X0\\/g, (_match, hex) =>
        (hex.match(/.{1,4}/g) ?? []).map((unit) => String.fromCharCode(parseInt(unit, 16))).join(""))
    .replace(/\\X\\([0-9A-Fa-f]{2})/g, (_match, hex) => String.fromCharCode(parseInt(hex, 16)))
    .replace(/''/g, "'");

/**
 * Splits the argument list of a STEP entity on its top level commas, leaving the commas that belong to a
 * nested list or to a quoted string alone.
 */
const splitStepArguments = (text) => {
    const args = [];
    let current = "";
    let depth = 0;
    let inString = false;

    for (let i = 0; i < text.length; i++) {
        const character = text[i];

        if (inString) {
            current += character;
            if (character === "'") {
                if (text[i + 1] === "'") {
                    //A doubled quote is an escaped quote, not the end of the string
                    current += "'";
                    i++;
                } else {
                    inString = false;
                }
            }
            continue;
        }

        if (character === "'") {
            inString = true;
        } else if (character === "(") {
            depth++;
        } else if (character === ")") {
            depth--;
        } else if (character === "," && depth === 0) {
            args.push(current.trim());
            current = "";
            continue;
        }

        current += character;
    }
    args.push(current.trim());

    return args;
};

/**
 * Reads a single STEP argument: a quoted string, a list of them, or "$"/"*" for a value the file does
 * not provide. Returns null for anything empty, so the caller can simply leave the row out.
 */
const stepValue = (raw) => {
    const value = (raw ?? "").trim();

    if (value === "" || value === "$" || value === "*") {
        return null;
    }
    if (value.startsWith("(")) {
        return splitStepArguments(value.slice(1, -1)).map(stepValue).filter(Boolean).join(", ") || null;
    }
    if (value.startsWith("'")) {
        return decodeStepString(value.slice(1, -1)).trim() || null;
    }

    return value;
};

/**
 * Returns the argument list of the named STEP entity, e.g. everything between the brackets of
 * "FILE_NAME( ... )". Quoted strings are skipped over when looking for the closing bracket.
 */
const stepEntityArguments = (text, name) => {
    const start = text.search(new RegExp(`\\b${name}\\s*\\(`, "i"));
    if (start === -1) {
        return null;
    }

    const open = text.indexOf("(", start);
    let depth = 0;
    let inString = false;

    for (let i = open; i < text.length; i++) {
        const character = text[i];

        if (inString) {
            if (character === "'") {
                if (text[i + 1] === "'") {
                    i++;
                } else {
                    inString = false;
                }
            }
        } else if (character === "'") {
            inString = true;
        } else if (character === "(") {
            depth++;
        } else if (character === ")") {
            depth--;
            if (depth === 0) {
                return text.slice(open + 1, i);
            }
        }
    }

    return null;
};

const geometryToObject = (geometry) => {
    if (geometry.attributes.normal === undefined) {
        geometry.computeVertexNormals();
    }

    return new THREE.Mesh(geometry, defaultMaterial(geometry.attributes.color !== undefined));
};

const defaultMaterial = (useVertexColors = false) => new THREE.MeshStandardMaterial({
    color: useVertexColors ? 0xffffff : 0xb0b8c0,
    vertexColors: useVertexColors,
    metalness: 0.15,
    roughness: 0.6,
    //CAD exports are full of inconsistently wound faces, showing both sides avoids holes in the model
    side: THREE.DoubleSide,
});

/* stimulusFetch: 'lazy' */
export default class extends Controller {
    static targets = ["container", "status", "info", "viewButton", "measureButton", "measurement", "measureHint", "metadata"];
    static values = {
        url: String,
        extension: String,
        filename: String,
    };

    _abortController = null;
    _worker = null;
    _resizeObserver = null;
    _themeObserver = null;
    _wireframe = false;
    _measuring = false;
    _measurePoints = [];
    _pointerDownAt = null;

    connect() {
        this.extension = this.extensionValue.toLowerCase();

        try {
            this._initScene();
        } catch {
            //Typically a browser (or VM) without a working WebGL implementation
            this._showError(trans("attachment.3d_viewer.webgl_unavailable"));
            return;
        }

        this._loadModel().catch((error) => {
            //The download is aborted when the controller disconnects, its elements are gone by then
            if (error.name === "AbortError") {
                return;
            }

            console.error(error);
            this._showError(trans("attachment.3d_viewer.load_error"));
        });
    }

    disconnect() {
        this._abortController?.abort();
        this._worker?.terminate();
        this._resizeObserver?.disconnect();
        this._themeObserver?.disconnect();

        this.controls?.dispose();

        if (this.renderer) {
            this.renderer.domElement.removeEventListener("pointerdown", this._onPointerDown);
            this.renderer.domElement.removeEventListener("pointerup", this._onPointerUp);
        }

        if (this.model) {
            this._disposeObject(this.model);
        }

        if (this._measureGroup) {
            this._disposeObject(this._measureGroup);
            this._measureGroup = null;
        }
        this._measurePoints = [];

        if (this.renderer) {
            this.renderer.setAnimationLoop(null);
            this.renderer.domElement.remove();
            this.renderer.dispose();
        }

        this._abortController = null;
        this._worker = null;
        this._resizeObserver = null;
        this._themeObserver = null;
        this.controls = null;
        this.renderer = null;
        this.model = null;
        this.scene = null;
    }

    /**
     * Frame the whole model again, after the user has zoomed or panned away.
     */
    resetView() {
        if (this.model) {
            this._fitCameraToModel();
            this._markActiveViewButton(null);
        }
    }

    /**
     * Jump to one of the preset views (see VIEW_DIRECTIONS). The distance to the model is kept, so
     * switching views while zoomed in stays zoomed in - use resetView() to frame the model again.
     */
    setView(event) {
        const direction = VIEW_DIRECTIONS[event.params.view];
        if (!direction || !this.model) {
            return;
        }

        const target = this.controls.target;
        const distance = this.camera.position.distanceTo(target);

        this.camera.position.copy(target)
            .add(new THREE.Vector3(...direction).normalize().multiplyScalar(distance));
        //OrbitControls clamps the polar angle itself, so looking straight down the Y axis is safe here
        this.controls.update();

        this._markActiveViewButton(event.currentTarget);
    }

    toggleWireframe() {
        this._wireframe = !this._wireframe;
        this._forEachMaterial((material) => {
            material.wireframe = this._wireframe;
        });
    }

    toggleGrid() {
        if (this.grid) {
            this.grid.visible = !this.grid.visible;
        }
    }

    toggleFullscreen() {
        if (document.fullscreenElement === this.containerTarget) {
            document.exitFullscreen();
        } else {
            this.containerTarget.requestFullscreen();
        }
    }

    /**
     * Saves what is currently on the canvas as a PNG. The measurement label is an HTML element floating
     * over the canvas rather than part of the scene, so it is drawn into the image separately - a
     * screenshot of a measurement is not much use without the number.
     */
    downloadScreenshot() {
        if (!this.renderer) {
            return;
        }

        //A WebGL drawing buffer is cleared once it has been composited, so it can only be read straight
        //after a render, without yielding in between. Everything up to toBlob() stays synchronous.
        this.renderer.render(this.scene, this.camera);

        const source = this.renderer.domElement;
        const canvas = document.createElement("canvas");
        canvas.width = source.width;
        canvas.height = source.height;

        const context = canvas.getContext("2d");
        context.drawImage(source, 0, 0);
        this._drawMeasurementLabel(context, source.width / this._canvasSize.width);

        canvas.toBlob((blob) => {
            if (blob) {
                this._download(blob, `${this._screenshotName()}.png`);
            }
        }, "image/png");
    }

    /**
     * Paints the distance badge onto the screenshot the way it appears over the canvas.
     */
    _drawMeasurementLabel(context, scale) {
        if (this._measurePoints.length < 2) {
            return;
        }

        const middle = new THREE.Vector3()
            .addVectors(this._measurePoints[0], this._measurePoints[1])
            .multiplyScalar(0.5);
        const {x, y} = this._toScreenPosition(middle);
        const text = this.measurementTarget.textContent;

        context.save();
        //Work in CSS pixels, so the badge keeps its size on a high DPI screen
        context.scale(scale, scale);
        context.font = "600 12px system-ui, -apple-system, sans-serif";
        context.textAlign = "center";
        context.textBaseline = "middle";

        const width = context.measureText(text).width + 12;
        const height = 20;
        const left = x - width / 2;
        const top = y - height / 2;

        context.fillStyle = `#${MEASUREMENT_COLOR.toString(16).padStart(6, "0")}`;
        if (context.roundRect) {
            context.beginPath();
            context.roundRect(left, top, width, height, 4);
            context.fill();
        } else {
            context.fillRect(left, top, width, height);
        }

        context.fillStyle = "#ffffff";
        context.fillText(text, x, y);
        context.restore();
    }

    /**
     * Names the screenshot after the attachment file, so several of them stay apart in the download folder.
     */
    _screenshotName() {
        const base = (this.filenameValue || "model").replace(/\.[^./\\]+$/, "").replace(/[\\/:*?"<>|]/g, "_");

        return base || "model";
    }

    _download(blob, filename) {
        const url = URL.createObjectURL(blob);
        const link = document.createElement("a");
        link.href = url;
        link.download = filename;
        link.click();

        //Only safe to release once the browser has picked the download up
        setTimeout(() => URL.revokeObjectURL(url), 0);
    }

    /**
     * Turns the measurement mode on and off. Orbiting keeps working while it is on, a click (as opposed
     * to a drag) places a measurement point instead of doing nothing.
     */
    toggleMeasure() {
        this._measuring = !this._measuring;

        this.measureButtonTarget.classList.toggle("active", this._measuring);
        this.measureButtonTarget.setAttribute("aria-pressed", this._measuring ? "true" : "false");
        this.measureHintTarget.classList.toggle("d-none", !this._measuring);

        if (this.renderer) {
            this.renderer.domElement.style.cursor = this._measuring ? "crosshair" : "";
        }
    }

    clearMeasurement() {
        this._measurePoints = [];

        if (this._measureGroup) {
            this._disposeObject(this._measureGroup);
            this._measureGroup.clear();
        }

        this.measurementTarget.classList.add("d-none");
    }

    /**
     * Places a measurement point where the click hit the model, snapping to a nearby corner if there is
     * one. The second point completes a measurement, a further click starts a new one.
     */
    _placeMeasurePoint(event) {
        const point = this._pickPoint(event);
        if (point === null) {
            return;
        }

        if (this._measurePoints.length >= 2) {
            this.clearMeasurement();
        }

        this._measurePoints.push(point);

        const marker = new THREE.Mesh(
            new THREE.SphereGeometry(this._modelRadius * 0.012, 16, 12),
            //Drawn on top of everything, so a point on a far face is not hidden by the model itself
            new THREE.MeshBasicMaterial({color: MEASUREMENT_COLOR, depthTest: false})
        );
        marker.position.copy(point);
        marker.renderOrder = 1000;
        this._measureGroup.add(marker);

        if (this._measurePoints.length === 2) {
            const line = new THREE.Line(
                new THREE.BufferGeometry().setFromPoints(this._measurePoints),
                new THREE.LineBasicMaterial({color: MEASUREMENT_COLOR, depthTest: false})
            );
            line.renderOrder = 1000;
            this._measureGroup.add(line);

            const distance = this._measurePoints[0].distanceTo(this._measurePoints[1]);
            this.measurementTarget.textContent = `${this._formatNumber(distance)} ${this._unit}`;
            this.measurementTarget.classList.remove("d-none");
        }
    }

    /**
     * Casts a ray through the clicked pixel and returns where it hit the model, or null if it missed.
     */
    _pickPoint(event) {
        const rect = this.renderer.domElement.getBoundingClientRect();
        const pointer = new THREE.Vector2(
            ((event.clientX - rect.left) / rect.width) * 2 - 1,
            -((event.clientY - rect.top) / rect.height) * 2 + 1
        );

        this._raycaster.setFromCamera(pointer, this.camera);
        const [hit] = this._raycaster.intersectObject(this.model, true);

        return hit ? this._snapToVertex(hit, event.clientX - rect.left, event.clientY - rect.top) : null;
    }

    /**
     * Measuring an edge or a hole is only useful if the points land exactly on the corners, so a hit
     * close to one of the corners of the hit triangle is pulled onto it.
     */
    _snapToVertex(hit, canvasX, canvasY) {
        if (!hit.face) {
            return hit.point;
        }

        const positions = hit.object.geometry.getAttribute("position");
        let snapped = hit.point;
        let closest = SNAP_DISTANCE_PX;

        for (const index of [hit.face.a, hit.face.b, hit.face.c]) {
            const vertex = hit.object.localToWorld(new THREE.Vector3().fromBufferAttribute(positions, index));
            const {x, y} = this._toScreenPosition(vertex);
            const distance = Math.hypot(x - canvasX, y - canvasY);

            if (distance < closest) {
                closest = distance;
                snapped = vertex;
            }
        }

        return snapped;
    }

    /**
     * Projects a world position onto the canvas, in pixels relative to its top left corner.
     */
    _toScreenPosition(position) {
        const projected = position.clone().project(this.camera);
        const {width, height} = this._canvasSize;

        return {
            x: ((projected.x + 1) / 2) * width,
            y: ((-projected.y + 1) / 2) * height,
        };
    }

    /**
     * Keeps the distance label on the middle of the measured line while the model is rotated.
     */
    _updateMeasurementLabel() {
        if (this._measurePoints.length < 2) {
            return;
        }

        const middle = new THREE.Vector3()
            .addVectors(this._measurePoints[0], this._measurePoints[1])
            .multiplyScalar(0.5);
        const {x, y} = this._toScreenPosition(middle);

        this.measurementTarget.style.left = `${x}px`;
        this.measurementTarget.style.top = `${y}px`;
    }

    _markActiveViewButton(button) {
        for (const viewButton of this.viewButtonTargets) {
            viewButton.classList.toggle("active", viewButton === button);
        }
    }

    _initScene() {
        const {clientWidth: width, clientHeight: height} = this.containerTarget;

        this.renderer = new THREE.WebGLRenderer({antialias: true});
        this.renderer.setPixelRatio(window.devicePixelRatio);
        this.renderer.setSize(width, height);
        this.containerTarget.appendChild(this.renderer.domElement);

        this.scene = new THREE.Scene();

        this.camera = new THREE.PerspectiveCamera(45, width / height, 0.1, 10000);
        this.camera.position.set(1, 1, 1);

        this.controls = new OrbitControls(this.camera, this.renderer.domElement);
        this.controls.enableDamping = true;

        this._canvasSize = {width, height};
        this._raycaster = new THREE.Raycaster();
        this._measureGroup = new THREE.Group();
        this.scene.add(this._measureGroup);

        this.renderer.domElement.addEventListener("pointerdown", this._onPointerDown);
        this.renderer.domElement.addEventListener("pointerup", this._onPointerUp);

        //A hemisphere light keeps the shaded side readable, the two directional lights give the model
        //enough contrast to make its edges visible from any angle
        this.scene.add(new THREE.HemisphereLight(0xffffff, 0x404050, 2.0));

        const keyLight = new THREE.DirectionalLight(0xffffff, 2.0);
        keyLight.position.set(1, 2, 3);
        this.scene.add(keyLight);

        const fillLight = new THREE.DirectionalLight(0xffffff, 0.8);
        fillLight.position.set(-2, -1, -2);
        this.scene.add(fillLight);

        this._applyTheme();
        this._watchTheme();

        this._resizeObserver = new ResizeObserver(() => this._resize());
        this._resizeObserver.observe(this.containerTarget);

        this.renderer.setAnimationLoop(() => {
            this.controls.update();
            this._updateMeasurementLabel();
            this.renderer.render(this.scene, this.camera);
        });
    }

    /**
     * Remembers where a drag started, so that orbiting the model is not mistaken for a measurement click.
     */
    _onPointerDown = (event) => {
        //Only the left button measures, the right one is used by OrbitControls to pan
        this._pointerDownAt = event.button === 0 ? {x: event.clientX, y: event.clientY} : null;
    };

    _onPointerUp = (event) => {
        const downAt = this._pointerDownAt;
        this._pointerDownAt = null;

        if (!this._measuring || !this.model || downAt === null || event.button !== 0) {
            return;
        }

        if (Math.hypot(event.clientX - downAt.x, event.clientY - downAt.y) <= CLICK_TOLERANCE_PX) {
            this._placeMeasurePoint(event);
        }
    };

    /**
     * Takes the scene colours from the theme, so that the viewer does not show a bright white box on a
     * dark theme. Both values are read from the container itself, which means they follow whichever
     * bootswatch theme and light/dark mode is active without us having to know any of them.
     */
    _applyTheme() {
        const style = getComputedStyle(this.containerTarget);

        this.renderer.setClearColor(new THREE.Color().setStyle(style.backgroundColor));

        if (this.grid) {
            //The body text colour contrasts with the background in either mode
            this.grid.material.color.setStyle(style.color);
        }
    }

    /**
     * Dark mode is applied by a separate controller writing data-bs-theme onto <html>, which can happen
     * after this controller has already started, and again whenever the user (or their system) switches
     * mode. So rather than reading the colours once, follow that attribute.
     */
    _watchTheme() {
        this._themeObserver = new MutationObserver(() => this._applyTheme());
        this._themeObserver.observe(document.documentElement, {
            attributes: true,
            attributeFilter: ["data-bs-theme"],
        });
    }

    async _loadModel() {
        this._abortController = new AbortController();

        const response = await fetch(this.urlValue, {signal: this._abortController.signal});
        if (!response.ok) {
            throw new Error(`Could not download the attachment file: ${response.status}`);
        }
        const buffer = await response.arrayBuffer();

        //Must happen before the buffer is transferred to the occt worker, which detaches it
        this._readFileMetadata(buffer);

        let object;
        if (this.extension in OCCT_FORMATS) {
            object = await this._parseWithOcct(buffer, OCCT_FORMATS[this.extension]);
        } else if (this.extension in MESH_PARSERS) {
            object = await MESH_PARSERS[this.extension](buffer);
        } else {
            this._showError(trans("attachment.3d_viewer.unsupported_format", {"%format%": this.extension}));
            return;
        }

        if (Z_UP_FORMATS.includes(this.extension)) {
            object.rotateX(-Math.PI / 2);
        }

        const gltfAsset = object.userData.gltfAsset;
        if (gltfAsset) {
            this._fileMetadata = {
                generator: gltfAsset.generator ?? null,
                version: gltfAsset.version ? `glTF ${gltfAsset.version}` : null,
                copyright: gltfAsset.copyright ?? null,
            };
        }

        this.model = object;
        this.scene.add(object);

        this._addGrid();
        this._applyTheme();
        this._fitCameraToModel();
        this._showInfo();

        this.statusTarget.classList.add("d-none");
    }

    /**
     * Tessellates a boundary representation file in a worker and converts the resulting triangle soup
     * into three.js meshes.
     */
    async _parseWithOcct(buffer, format) {
        this._worker = new Worker(new URL("../../js/workers/occt_worker.js", import.meta.url));

        const meshes = await new Promise((resolve, reject) => {
            this._worker.onmessage = (event) => {
                if (event.data.success) {
                    resolve(event.data.meshes);
                } else {
                    reject(new Error(event.data.error));
                }
            };
            this._worker.onerror = () => reject(new Error("The occt-import-js worker could not be started"));

            this._worker.postMessage({format, buffer}, [buffer]);
        });

        this._worker.terminate();
        this._worker = null;

        const group = new THREE.Group();
        for (const mesh of meshes) {
            const geometry = new THREE.BufferGeometry();
            geometry.setAttribute("position", new THREE.Float32BufferAttribute(mesh.attributes.position.array, 3));
            if (mesh.attributes.normal) {
                geometry.setAttribute("normal", new THREE.Float32BufferAttribute(mesh.attributes.normal.array, 3));
            } else {
                geometry.computeVertexNormals();
            }
            geometry.setIndex(new THREE.BufferAttribute(Uint32Array.from(mesh.index.array), 1));

            const material = defaultMaterial();
            if (mesh.color) {
                material.color = new THREE.Color(mesh.color[0], mesh.color[1], mesh.color[2]);
            }

            const threeMesh = new THREE.Mesh(geometry, material);
            threeMesh.name = mesh.name ?? "";
            group.add(threeMesh);
        }

        return group;
    }

    /**
     * Puts a grid below the model, scaled to a power of ten that roughly matches the model size, so that
     * it gives an impression of the model's proportions instead of drowning it in lines.
     */
    _addGrid() {
        const box = new THREE.Box3().setFromObject(this.model);
        const size = box.getSize(new THREE.Vector3());
        const center = box.getCenter(new THREE.Vector3());

        const extent = Math.max(size.x, size.z, 1e-3);
        const step = 10 ** Math.round(Math.log10(extent / 10));
        const divisions = Math.max(Math.ceil(extent * 2 / step), 2);

        this.grid = new THREE.GridHelper(divisions * step, divisions);
        //GridHelper bakes its two colours into a vertex attribute; turning that off lets _applyTheme()
        //drive the whole grid colour from the theme instead of merely tinting the baked grey
        this.grid.material.vertexColors = false;
        this.grid.material.needsUpdate = true;
        this.grid.material.opacity = 0.35;
        this.grid.material.transparent = true;
        //Sit the grid right below the model instead of at the world origin, which can be far away
        this.grid.position.set(center.x, box.min.y, center.z);
        this.scene.add(this.grid);
    }

    _fitCameraToModel() {
        const box = new THREE.Box3().setFromObject(this.model);
        if (box.isEmpty()) {
            return;
        }

        const center = box.getCenter(new THREE.Vector3());
        const radius = Math.max(box.getBoundingSphere(new THREE.Sphere()).radius, 1e-3);
        this._modelRadius = radius;

        //Distance at which a sphere of this radius fills the (vertical) field of view, with a bit of margin
        const distance = 1.4 * radius / Math.sin(THREE.MathUtils.degToRad(this.camera.fov) / 2);

        this.camera.near = distance / 100;
        this.camera.far = distance * 100;
        this.camera.position.copy(center).add(new THREE.Vector3(1, 0.8, 1).normalize().multiplyScalar(distance));
        this.camera.updateProjectionMatrix();

        this.controls.target.copy(center);
        this.controls.update();
    }

    /**
     * Fills the badge on the canvas and the metadata dialog. Both are built from the same walk over the
     * model, as counting its triangles already means visiting all of them.
     */
    _showInfo() {
        const box = new THREE.Box3().setFromObject(this.model);
        const size = box.getSize(new THREE.Vector3());
        const stats = this._geometryStats();
        const format = (value) => this._formatNumber(value);
        const dimensions = `${format(size.x)} × ${format(size.y)} × ${format(size.z)} ${this._unit}`;

        this.infoTarget.textContent = trans("attachment.3d_viewer.info", {
            "%dimensions%": dimensions,
            "%triangles%": format(stats.triangles),
        });
        this.infoTarget.classList.remove("d-none");

        this._renderMetadata(dimensions, stats);
    }

    /**
     * Counts what the model is made of, and measures it. Volume and surface area come from summing the
     * signed tetrahedra and the areas of every triangle, which is only exact for a closed mesh - hence
     * the note next to them in the dialog.
     */
    _geometryStats() {
        const materials = new Set();
        let triangles = 0;
        let vertices = 0;
        let meshes = 0;

        this.model.updateWorldMatrix(false, true);
        this.model.traverse((child) => {
            if (!child.isMesh) {
                return;
            }

            meshes++;
            for (const material of Array.isArray(child.material) ? child.material : [child.material]) {
                if (material) {
                    materials.add(material);
                }
            }

            const position = child.geometry.getAttribute("position");
            if (!position) {
                return;
            }

            vertices += position.count;
            const index = child.geometry.getIndex();
            triangles += (index ? index.count : position.count) / 3;
        });

        const stats = {triangles: Math.round(triangles), vertices, meshes, materials: materials.size, volume: null, area: null};

        if (stats.triangles <= MAX_TRIANGLES_FOR_VOLUME) {
            Object.assign(stats, this._measureSurface());
        }

        return stats;
    }

    _measureSurface() {
        const a = new THREE.Vector3();
        const b = new THREE.Vector3();
        const c = new THREE.Vector3();
        const ab = new THREE.Vector3();
        const ac = new THREE.Vector3();
        const cross = new THREE.Vector3();

        let volume = 0;
        let area = 0;

        this.model.traverse((child) => {
            if (!child.isMesh) {
                return;
            }

            const position = child.geometry.getAttribute("position");
            if (!position) {
                return;
            }

            const index = child.geometry.getIndex();
            const count = index ? index.count : position.count;

            for (let i = 0; i < count; i += 3) {
                a.fromBufferAttribute(position, index ? index.getX(i) : i).applyMatrix4(child.matrixWorld);
                b.fromBufferAttribute(position, index ? index.getX(i + 1) : i + 1).applyMatrix4(child.matrixWorld);
                c.fromBufferAttribute(position, index ? index.getX(i + 2) : i + 2).applyMatrix4(child.matrixWorld);

                //The signed volumes of the tetrahedra spanned with the origin add up to the enclosed volume
                volume += a.dot(cross.crossVectors(b, c)) / 6;
                area += ab.subVectors(b, a).cross(ac.subVectors(c, a)).length() / 2;
            }
        });

        return {volume: Math.abs(volume), area};
    }

    /**
     * Builds the table shown by the metadata dialog: what the file is, what the model is made of, and
     * whatever the file itself had to say about where it came from.
     */
    _renderMetadata(dimensions, stats) {
        const label = (key) => trans(`attachment.3d_viewer.metadata.${key}`);
        const format = (value) => this._formatNumber(value);
        const unitNote = trans(this._unitIsAssumed ? "attachment.3d_viewer.unit.assumed" : "attachment.3d_viewer.unit.from_file");

        const sections = [
            {
                title: label("file"),
                rows: [
                    [label("format"), this.extension.toUpperCase()],
                    [label("file_size"), this._formatFileSize(this._fileSize)],
                    [label("unit"), `${this._unit} (${unitNote})`],
                ],
            },
            {
                title: label("geometry"),
                rows: [
                    [label("dimensions"), dimensions],
                    [label("volume"), stats.volume === null ? null : `${format(stats.volume)} ${this._unit}³ (${label("approximate")})`],
                    [label("surface_area"), stats.area === null ? null : `${format(stats.area)} ${this._unit}²`],
                    [label("triangles"), format(stats.triangles)],
                    [label("vertices"), format(stats.vertices)],
                    [label("objects"), format(stats.meshes)],
                    [label("materials"), format(stats.materials)],
                ],
            },
            {
                title: label("source"),
                rows: Object.entries(this._fileMetadata ?? {}).map(([key, value]) => [label(key), value]),
            },
        ];

        this.metadataTarget.replaceChildren();

        for (const section of sections) {
            const rows = section.rows.filter(([, value]) => value !== null && value !== undefined && value !== "");
            if (rows.length === 0) {
                continue;
            }

            const heading = document.createElement("tr");
            const headingCell = document.createElement("th");
            headingCell.colSpan = 2;
            headingCell.className = "table-active";
            headingCell.textContent = section.title;
            heading.append(headingCell);
            this.metadataTarget.append(heading);

            for (const [name, value] of rows) {
                const row = document.createElement("tr");

                const key = document.createElement("th");
                key.scope = "row";
                key.className = "fw-normal text-muted";
                key.style.width = "35%";
                key.textContent = name;

                const cell = document.createElement("td");
                //Everything here comes out of an uploaded file, so it is only ever set as text
                cell.textContent = value;

                row.append(key, cell);
                this.metadataTarget.append(row);
            }
        }
    }

    _formatFileSize(bytes) {
        if (!bytes) {
            return null;
        }

        const units = ["B", "KiB", "MiB", "GiB"];
        let size = bytes;
        let unit = 0;
        while (size >= 1024 && unit < units.length - 1) {
            size /= 1024;
            unit++;
        }

        return `${this._formatNumber(size)} ${units[unit]}`;
    }

    /**
     * Reads everything that can only be got from the raw file, before the buffer is handed (and thereby
     * transferred) to the occt worker: its size, the unit the coordinates are in, and whatever the format
     * records about the program that wrote it.
     */
    _readFileMetadata(buffer) {
        this._fileSize = buffer.byteLength;
        this._fileMetadata = {};
        this._unit = FORMAT_UNITS[this.extension] ?? null;
        this._unitIsAssumed = false;

        if (this.extension === "step" || this.extension === "stp") {
            const text = decodeText(buffer);
            this._unit ??= this._detectStepUnit(text);
            this._fileMetadata = this._parseStepHeader(text);
        } else if (this.extension === "stl") {
            this._fileMetadata = {header: this._readStlHeader(buffer)};
        }

        if (this._unit === null) {
            this._unit = FALLBACK_UNIT;
            this._unitIsAssumed = true;
        }

        const title = trans(this._unitIsAssumed ? "attachment.3d_viewer.unit.assumed" : "attachment.3d_viewer.unit.from_file");
        this.infoTarget.title = title;
        this.measurementTarget.title = title;
    }

    /**
     * Reads the HEADER section of a STEP file, which records who wrote the file, when, and with which CAD
     * program - by far the most interesting metadata any of the supported formats carries.
     */
    _parseStepHeader(text) {
        const section = text.match(/\bHEADER\s*;([\s\S]*?)\bENDSEC\s*;/i);
        if (!section) {
            return {};
        }

        //Strip the /* ... */ comments real exporters put in front of every argument
        const header = section[1].replace(/\/\*[\s\S]*?\*\//g, " ");

        const description = splitStepArguments(stepEntityArguments(header, "FILE_DESCRIPTION") ?? "");
        const name = splitStepArguments(stepEntityArguments(header, "FILE_NAME") ?? "");
        const schema = splitStepArguments(stepEntityArguments(header, "FILE_SCHEMA") ?? "");

        return {
            description: stepValue(description[0]),
            name: stepValue(name[0]),
            timestamp: stepValue(name[1]),
            author: stepValue(name[2]),
            organization: stepValue(name[3]),
            preprocessor: stepValue(name[4]),
            originating_system: stepValue(name[5]),
            authorisation: stepValue(name[6]),
            schema: stepValue(schema[0]),
        };
    }

    /**
     * The 80 byte header of a binary STL is free text and usually names the program that exported it,
     * while an ASCII STL only carries the name given to its solid.
     */
    _readStlHeader(buffer) {
        if (buffer.byteLength >= 84) {
            const triangles = new DataView(buffer).getUint32(80, true);
            if (84 + triangles * 50 === buffer.byteLength) {
                return decodeText(new Uint8Array(buffer, 0, 80)).replace(/\0/g, "").trim() || null;
            }
        }

        const start = decodeText(new Uint8Array(buffer, 0, Math.min(buffer.byteLength, 256)));
        return start.match(/^\s*solid\s+(.+)/i)?.[1].trim() || null;
    }

    /**
     * Works out which unit the numbers we show are in. STEP files declare their length unit, everything
     * else either has a unit fixed by its format (FORMAT_UNITS) or none at all, in which case millimeters
     * are assumed - which is what CAD and 3D printing files without a unit practically always use.
     *
     * Reads the length unit out of a STEP file, which declares it as part of its geometric context, e.g.
     * "( LENGTH_UNIT() NAMED_UNIT(*) SI_UNIT(.MILLI.,.METRE.) )" for millimeters, or as a conversion based
     * unit named INCH. Returns null if neither can be found.
     */
    _detectStepUnit(text) {
        //A length unit given in metres, optionally with an SI prefix (no prefix means plain metres)
        const si = text.match(/LENGTH_UNIT\s*\(\s*\)[\s\S]{0,200}?SI_UNIT\s*\(\s*([^,\s]+)\s*,\s*\.METRE\.\s*\)/i)
            ?? text.match(/SI_UNIT\s*\(\s*([^,\s]+)\s*,\s*\.METRE\.\s*\)[\s\S]{0,200}?LENGTH_UNIT\s*\(\s*\)/i);
        if (si) {
            const prefix = si[1].toUpperCase();
            return prefix === "$" ? "m" : (STEP_SI_PREFIXES[prefix] ?? null);
        }

        //Imperial files instead convert from an SI unit, naming the result INCH or FOOT
        const converted = text.match(/CONVERSION_BASED_UNIT\s*\(\s*'\s*(INCH|FOOT)[^']*'/i);
        if (converted) {
            return converted[1].toUpperCase() === "INCH" ? "in" : "ft";
        }

        return null;
    }

    _formatNumber(value) {
        return value.toLocaleString(document.body.dataset.locale ?? undefined, {maximumFractionDigits: 2});
    }

    _showError(message) {
        this.statusTarget.innerHTML = "";

        const icon = document.createElement("i");
        icon.className = "fa-solid fa-triangle-exclamation fa-2x text-warning";

        const text = document.createElement("div");
        text.className = "mt-2";
        text.textContent = message;

        this.statusTarget.append(icon, text);
        this.statusTarget.classList.remove("d-none");
    }

    _resize() {
        const {clientWidth: width, clientHeight: height} = this.containerTarget;
        if (width === 0 || height === 0) {
            return;
        }

        this._canvasSize = {width, height};
        this.camera.aspect = width / height;
        this.camera.updateProjectionMatrix();
        this.renderer.setSize(width, height);
    }

    _forEachMaterial(callback) {
        this.model?.traverse((child) => {
            if (!child.isMesh) {
                return;
            }

            for (const material of Array.isArray(child.material) ? child.material : [child.material]) {
                callback(material);
            }
        });
    }

    _disposeObject(object) {
        object.traverse((child) => {
            child.geometry?.dispose();

            if (child.material) {
                for (const material of Array.isArray(child.material) ? child.material : [child.material]) {
                    material.dispose();
                }
            }
        });
    }
}
