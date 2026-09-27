---
layout: default
title: 3D model viewer
parent: Usage
---

# 3D model viewer

Part-DB comes with a built-in **interactive 3D model viewer** for attachments. When a 3D model (like the STEP model
of a footprint or a part) is stored as an attachment, you can inspect it directly in the browser,
without installing any CAD software.

The viewer runs completely in your browser (using [three.js](https://threejs.org/) and
[OpenCascade](https://github.com/kovacsv/occt-import-js)). The model file is not sent to any external service.

## Opening a model

Upload a 3D model file as an attachment of a part, footprint or other element. Then click the **View** button
(the eye icon) of the attachment in the attachments list. Part-DB opens the 3D model viewer instead of letting
the browser download the file.

The viewer only works for attachments whose file is **stored locally** in Part-DB. If an attachment only
contains an external URL, download the file first (for example by enabling "Download external file" in the attachment edit
form, or with the `partdb:attachments:download` console command).

Your browser needs [WebGL](https://get.webgl.org/) support. All current desktop and mobile browsers
support it, but it may be unavailable in some virtual machines or when hardware acceleration is disabled.

## Supported file formats

Part-DB decides by the file extension whether a file is a 3D model:

| Type                                    | Extensions                                                                                               |
|-----------------------------------------|----------------------------------------------------------------------------------------------------------|
| CAD formats (boundary representation)   | `.step`, `.stp`, `.iges`, `.igs`, `.brep`, `.brp`                                                        |
| Mesh formats                            | `.stl`, `.obj`, `.ply`, `.3mf`, `.gltf`, `.glb`, `.dae`, `.fbx`, `.wrl`, `.vrml`, `.amf`, `.3ds`, `.vtk` |

Some limitations:

* Only the model file itself is loaded. Companion files are ignored, like the `.mtl` material file of an OBJ
  model or external buffers and textures of a `.gltf` file. Use self-contained files (e.g. `.glb`) if you need
  materials and textures.
* CAD formats (STEP, IGES, BREP) are converted to triangles in your browser before they are shown. The
  converter is only downloaded when you open such a file, which takes a moment the first time.
  Large, complex assemblies can take a while to load.

## Navigation

* **Left mouse button + drag** rotates the model.
* **Scroll wheel** zooms in and out.
* **Right mouse button + drag** pans the view.

The buttons at the top select one of the **standard views**: isometric, front, back, left, right, top and bottom.
Models in formats where Z points up (STL, 3MF, AMF, STEP, IGES, BREP) are rotated automatically, so "top" shows
the top of the part and it isn't lying on its side.

## Toolbar

The toolbar on the right side of the viewer contains these tools (from top to bottom):

* **Reset view**: Fits the whole model into the view again.
* **Toggle wireframe**: Shows the triangles of the model's surface as a wireframe.
* **Toggle grid**: Shows or hides the ground grid.
* **Measure**: Measures the distance between two points (see below).
* **Clear measurement**: Removes the current measurement.
* **Parts**: Shows or hides single components of the model. This button only appears if the model
  consists of multiple components (like a STEP assembly of a connector with its housing and pins).
  Use **All** / **None** to show or hide all components at once.
* **Download screenshot**: Saves the current view as a PNG image. The image includes a visible measurement.
* **Model information**: Shows details about the file and the model (see below).
* **Toggle fullscreen**: Shows the viewer in fullscreen mode.

## Measuring distances

Activate the measure tool and click on two points of the model. The viewer draws a line between them and shows
the distance. A point snaps to a nearby corner of the model, so it's easy to measure exact
edge lengths, pin pitches and similar dimensions. Hidden components are ignored when you pick
points.

### Units

The distance is shown in the unit of the model file:

* **STEP** files store their unit (e.g. millimeters or inches), which is read from the file.
* **glTF/GLB** and **COLLADA** (`.dae`) files are always in meters, and **AMF** files in millimeters.
* All other formats (like STL or OBJ) do not store a unit. For them, **millimeters are assumed**, which is
  the usual convention for electronic components. Hover over the size info or the measured distance to see whether the unit was
  read from the file or assumed.

## Model information

The model information dialog shows data about the loaded file:

* the file name, format, file size and length unit,
* the geometry: bounding box dimensions, volume, surface area, number of triangles, vertices, objects and
  materials. The volume is only exact if the model is a closed solid. Volume and surface area aren't
  calculated for very large meshes.
* metadata stored in the file, if the format has it. STEP files contain the author, organization, CAD
  system, exporter and schema in their header. glTF files contain the generator, version and copyright, and STL
  files contain a free-text header.
