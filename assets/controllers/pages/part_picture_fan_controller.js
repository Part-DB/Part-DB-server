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
import {Carousel} from "bootstrap";

import "../../css/components/part_picture_fan.css";

/**
 * Fans out all pictures of a part next to the picture carousel of the part info page, while the mouse is over it.
 * The fanning itself is pure CSS (see part_picture_fan.css); this controller shows the clicked picture in the
 * carousel and, if an URL is given (the user may edit the part), saves it as the preview picture of the part.
 */
export default class extends Controller {
    static targets = ["carousel", "item"];

    static values = {
        url: String,
        csrf: String,
        master: Number,
    };

    connect() {
        this._onSlide = (event) => this._markActive(event.to);
        this.carouselTarget.addEventListener("slide.bs.carousel", this._onSlide);
    }

    disconnect() {
        this.carouselTarget.removeEventListener("slide.bs.carousel", this._onSlide);
    }

    select(event) {
        const item = event.currentTarget;
        const index = parseInt(item.dataset.index, 10);

        Carousel.getOrCreateInstance(this.carouselTarget).to(index);
        this._markActive(index);

        const attachmentId = parseInt(item.dataset.attachmentId ?? "", 10);
        if (!this.hasUrlValue || !this.urlValue || Number.isNaN(attachmentId) || attachmentId === this.masterValue) {
            return;
        }

        this._save(item, attachmentId);
    }

    _markActive(index) {
        this.itemTargets.forEach((item) => {
            item.classList.toggle("active", parseInt(item.dataset.index, 10) === index);
        });
    }

    _save(item, attachmentId) {
        const body = new FormData();
        body.append("attachment", attachmentId);
        body.append("_token", this.csrfValue);

        item.classList.add("is-saving");
        fetch(this.urlValue, {
            method: "POST",
            body,
            headers: {"X-Requested-With": "XMLHttpRequest"},
        })
            .then((response) => response.json().then((data) => ({ok: response.ok, data})))
            .then(({ok, data}) => {
                if (!ok || !data || !data.success) {
                    throw new Error("Unexpected response");
                }
                this.masterValue = attachmentId;
                this.itemTargets.forEach((other) => other.classList.toggle("is-master", other === item));
            })
            .catch((error) => {
                console.warn("Could not save the preview picture of the part", error);
            })
            .finally(() => {
                item.classList.remove("is-saving");
            });
    }
}
