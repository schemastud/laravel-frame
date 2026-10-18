<?php

namespace Schemastud\Frame\Http\Controllers;

/**
 * Method-less MARKER: this controller IS frame's shared resource socket. Frame owns the fact and
 * exports it here (architecture-pass A4-C4, owner-ruled 2026-10-18); before this, "is this request
 * served by the socket" was a hand-maintained list of three controller class-strings duplicated at
 * three beam sites, so a future socket controller (the ADR-0005 actions direction) would silently
 * escape beam's write-gate helper and its authorization audit.
 *
 * A new socket controller opts in HERE, at its declaration — consumers above frame (beam's
 * `FrameSocketRoute`, its `ScopedReadAuthenticationAudit`) recognize the FAMILY through this marker
 * and need no edit. Per-member dispatch (filters vs summary vs CRUD) rightly stays by concrete
 * class where a consumer needs the member, not the family.
 */
interface ServesResourceSocket {}
