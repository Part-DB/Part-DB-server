/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2022 Jan Böhmer (https://github.com/jbtronics)
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

import Swal from 'sweetalert2';
import 'sweetalert2/themes/bootstrap-5.css';
import '../css/components/swal.css'
import { trans } from '../translator';

const BaseSwal = Swal.mixin({
    position: "top",
    theme: "bootstrap-5",
    confirmButtonText: trans('dialog.btn.ok'),
    cancelButtonText: trans('dialog.btn.cancel'),
    denyButtonText: trans('dialog.btn.deny'),
});

const ConfirmSwal = BaseSwal.mixin({
    showCancelButton: true,
    showCloseButton: true,
    icon: "warning",
});

const AlertSwal = BaseSwal.mixin({
    showCloseButton: true,
    icon: "info",
});

export { ConfirmSwal, AlertSwal, BaseSwal, BaseSwal as default,};
