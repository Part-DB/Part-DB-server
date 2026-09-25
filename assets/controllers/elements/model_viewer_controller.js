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

/** How close (in screen pixels) a corner has to be for a measurement point to snap onto it. */
const SNAP_DISTANCE_PX = 12;

/** A pointer that moved further than this between press and release was a drag (orbiting), not a click. */
const CLICK_TOLERANCE_PX = 4;

const MEASUREMENT_COLOR = 0xdc3545;

const decodeText = (buffer) => new TextDecoder().decode(buffer);

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
    static targets = ["container", "status", "info", "viewButton", "measureButton", "measurement", "measureHint"];
    static values = {
        url: String,
        extension: String,
    };

    _abortController = null;
    _worker = null;
    _resizeObserver = null;
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

            this.measurementTarget.textContent = this._formatNumber(this._measurePoints[0].distanceTo(this._measurePoints[1]));
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

        this._applyThemeBackground();

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
     * Use the background the (bootswatch) theme gives our container, so that the viewer does not show a
     * bright white box on a dark theme.
     */
    _applyThemeBackground() {
        const background = new THREE.Color().setStyle(getComputedStyle(this.containerTarget).backgroundColor);
        this.renderer.setClearColor(background);
    }

    async _loadModel() {
        this._abortController = new AbortController();

        const response = await fetch(this.urlValue, {signal: this._abortController.signal});
        if (!response.ok) {
            throw new Error(`Could not download the attachment file: ${response.status}`);
        }
        const buffer = await response.arrayBuffer();

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

        this.model = object;
        this.scene.add(object);

        this._addGrid();
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

        this.grid = new THREE.GridHelper(divisions * step, divisions, 0x888888, 0x888888);
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

    _showInfo() {
        const size = new THREE.Box3().setFromObject(this.model).getSize(new THREE.Vector3());

        let triangles = 0;
        this.model.traverse((child) => {
            if (child.isMesh) {
                const index = child.geometry.getIndex();
                triangles += (index ? index.count : child.geometry.getAttribute("position").count) / 3;
            }
        });

        const format = (value) => this._formatNumber(value);

        this.infoTarget.textContent = trans("attachment.3d_viewer.info", {
            "%dimensions%": `${format(size.x)} × ${format(size.y)} × ${format(size.z)}`,
            "%triangles%": format(Math.round(triangles)),
        });
        this.infoTarget.classList.remove("d-none");
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
